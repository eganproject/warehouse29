<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QcScanResi;
use App\Models\QcScanResiItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QcScanHistoryController extends Controller
{
    public function index()
    {
        $authUser = request()->user();
        $userQuery = User::orderBy('name');
        if ($authUser) {
            $divisiId = $authUser->divisi_id;
            if ($divisiId !== null && (int) $divisiId !== 1) {
                $userQuery->where('divisi_id', $divisiId);
            }
        }
        $users = $userQuery->get(['id', 'name']);

        return view('admin.outbound.qc-scan-history.index', [
            'dataUrl' => route('admin.outbound.qc-scan-history.data'),
            'users' => $users,
            'today' => now()->toDateString(),
            'generatedBy' => $authUser?->name ?? '-',
        ]);
    }

    public function data(Request $request)
    {
        $authUser = $request->user();

        $baseQuery = QcScanResi::query();
        if ($authUser) {
            $divisiId = $authUser->divisi_id;
            if ($divisiId !== null && (int) $divisiId !== 1) {
                $baseQuery->whereHas('scanner', function ($q) use ($divisiId) {
                    $q->where('divisi_id', $divisiId);
                });
            }
        }

        $filtered = $this->applyFilters(clone $baseQuery, $request);

        $recordsTotal = (clone $baseQuery)->count();
        $recordsFiltered = (clone $filtered)->count();
        $completedTotal = (clone $filtered)->where('status', 'completed')->count();
        $inProgressTotal = max(0, $recordsFiltered - $completedTotal);

        $qtyAgg = QcScanResiItem::query()
            ->whereIn('qc_scan_resi_id', (clone $filtered)->select('id'))
            ->selectRaw('COALESCE(SUM(required_qty), 0) as req, COALESCE(SUM(scanned_qty), 0) as scn')
            ->first();
        $summaryRequiredQty = (int) ($qtyAgg->req ?? 0);
        $summaryScannedQty = (int) ($qtyAgg->scn ?? 0);
        $summaryProgress = $summaryRequiredQty > 0
            ? (int) floor(min(100, $summaryScannedQty / $summaryRequiredQty * 100))
            : 0;

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);

        $rowsQuery = (clone $filtered)
            ->with(['scanner:id,name', 'resi:id,no_resi,id_pesanan', 'items.item:id,name'])
            ->orderBy('scanned_at', 'desc')
            ->orderBy('id', 'desc');
        if ($length > 0) {
            $rowsQuery->skip($start)->take($length);
        }

        $data = $rowsQuery->get()->values()->map(function ($row, $index) use ($start) {
            $ledgerItems = $row->items ?? collect();
            $requiredQty = (int) $ledgerItems->sum('required_qty');
            $scannedQty = (int) $ledgerItems->sum('scanned_qty');
            $qcProgress = $requiredQty > 0 ? (int) floor(min(100, ($scannedQty / $requiredQty) * 100)) : 0;
            $items = $ledgerItems->map(fn ($it) => [
                'sku' => $it->sku,
                'name' => $it->item?->name ?? '-',
                'required_qty' => (int) $it->required_qty,
                'scanned_qty' => (int) $it->scanned_qty,
                'status' => (int) $it->scanned_qty >= (int) $it->required_qty ? 'completed' : 'in_progress',
            ])->values();
            $labels = $items
                ->map(fn ($item) => sprintf('%s (%d/%d)', $item['sku'], $item['scanned_qty'], $item['required_qty']))
                ->values();

            return [
                'no'           => $start + $index + 1,
                'id'           => $row->id,
                'no_resi'      => $row->resi?->no_resi ?? '-',
                'id_pesanan'   => $row->resi?->id_pesanan ?? '-',
                'picker'       => $row->scanner?->name ?? '-',
                'status'       => $row->status,
                'scanned_at'   => $this->formatDateTime($row->scanned_at),
                'completed_at' => $this->formatDateTime($row->completed_at),
                'item'         => $labels->implode(', ') ?: '-',
                'sku_count'    => $items->count(),
                'items'        => $items,
                'required_qty' => $requiredQty,
                'scanned_qty'  => $scannedQty,
                'qc_progress'  => $qcProgress,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'summary' => [
                'total_resi'   => $recordsFiltered,
                'completed'    => $completedTotal,
                'in_progress'  => $inProgressTotal,
                'required_qty' => $summaryRequiredQty,
                'scanned_qty'  => $summaryScannedQty,
                'progress_pct' => $summaryProgress,
            ],
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        DB::beginTransaction();
        try {
            $qcResi = QcScanResi::where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            $authUser = $request->user();
            if ($authUser && $authUser->divisi_id !== null && (int) $authUser->divisi_id !== 1) {
                $qcResi->loadMissing('scanner:id,divisi_id');
                if ((int) $qcResi->scanner?->divisi_id !== (int) $authUser->divisi_id) {
                    DB::rollBack();
                    return response()->json(['message' => 'Tidak diizinkan'], 403);
                }
            }

            if ($qcResi->status !== 'in_progress') {
                DB::rollBack();
                return response()->json(['message' => 'Resi QC sudah selesai dan tidak bisa dihapus'], 422);
            }

            $qcResi->load('items');
            $hasScannedQty = $qcResi->items->sum('scanned_qty') > 0;
            if ($hasScannedQty) {
                DB::rollBack();
                return response()->json(['message' => 'Resi sudah berisi scan SKU, tidak bisa dihapus'], 422);
            }

            $qcResi->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus resi QC',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Resi QC berhasil dihapus',
        ]);
    }

    private function applyFilters($query, Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('scanner', function ($userQ) use ($search) {
                    $userQ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('no_resi', 'like', "%{$search}%")
                            ->orWhere('id_pesanan', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%");
                    });
            });
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('scanned_by', $userId);
        }

        $status = $request->input('status');
        if (in_array($status, ['in_progress', 'completed'], true)) {
            $query->where('status', $status);
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        try {
            if ($dateFrom) {
                $query->where('scanned_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            }
            if ($dateTo) {
                $query->where('scanned_at', '<=', Carbon::parse($dateTo)->endOfDay());
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }

        return $query;
    }

    private function formatDateTime($value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->locale('id')->translatedFormat('d M Y, H:i');
        } catch (\Throwable) {
            return '';
        }
    }
}
