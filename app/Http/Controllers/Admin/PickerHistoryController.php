<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QcScanSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PickerHistoryController extends Controller
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

        return view('admin.outbound.picker-sessions.index', [
            'dataUrl' => route('admin.outbound.picker-sessions.data'),
            'users' => $users,
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $authUser = $request->user();

        $baseQuery = QcScanSession::query()
            ->with(['user', 'resis.resi', 'resis.items.item'])
            ->orderBy('started_at', 'desc');

        if ($authUser) {
            $divisiId = $authUser->divisi_id;
            if ($divisiId !== null && (int) $divisiId !== 1) {
                $baseQuery->whereHas('user', function ($q) use ($divisiId) {
                    $q->where('divisi_id', $divisiId);
                });
            }
        }

        $query = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('resis.resi', function ($resiQ) use ($search) {
                        $resiQ->where('no_resi', 'like', "%{$search}%")
                            ->orWhere('id_pesanan', 'like', "%{$search}%");
                    })
                    ->orWhereHas('resis.items', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%");
                    });
            });
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        $status = $request->input('status');
        if (in_array($status, ['active', 'closed'], true)) {
            $query->where('status', $status);
        }

        $this->applyDateFilter($query, $request);

        $recordsTotal = (clone $baseQuery)->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $qcResis = $row->resis ?? collect();
            $started   = $row->started_at   ? Carbon::parse($row->started_at)->format('Y-m-d H:i')   : '';
            $lastScan = $row->last_scan_at ? Carbon::parse($row->last_scan_at)->format('Y-m-d H:i') : '';

            $resiList = $qcResis->map(function ($qsr) {
                $resi = $qsr->resi;
                $ledgerItems = $qsr->items ?? collect();
                $requiredQty = (int) $ledgerItems->sum('required_qty');
                $scannedQty = (int) $ledgerItems->sum('scanned_qty');
                $progress = $requiredQty > 0 ? (int) floor(min(100, ($scannedQty / $requiredQty) * 100)) : 0;

                return [
                    'id' => $qsr->id,
                    'no_resi'    => $resi?->no_resi    ?? '-',
                    'id_pesanan' => $resi?->id_pesanan ?? '-',
                    'status' => $qsr->status ?? 'in_progress',
                    'scanned_at' => $qsr->scanned_at ? Carbon::parse($qsr->scanned_at)->format('Y-m-d H:i') : '-',
                    'completed_at' => $qsr->completed_at ? Carbon::parse($qsr->completed_at)->format('Y-m-d H:i') : '',
                    'required_qty' => $requiredQty,
                    'scanned_qty' => $scannedQty,
                    'progress' => $progress,
                    'items' => $ledgerItems->map(fn ($it) => [
                        'sku' => $it->sku,
                        'name' => $it->item?->name ?? '-',
                        'required_qty' => (int) $it->required_qty,
                        'scanned_qty' => (int) $it->scanned_qty,
                        'status' => (int) $it->scanned_qty >= (int) $it->required_qty ? 'completed' : 'in_progress',
                    ])->values(),
                ];
            })->values();
            $completedResi = $resiList->where('status', 'completed')->count();
            $inProgressResi = $resiList->where('status', '!=', 'completed')->count();
            $requiredQty = (int) $resiList->sum('required_qty');
            $scannedQty = (int) $resiList->sum('scanned_qty');
            $qcProgress = $requiredQty > 0 ? (int) floor(min(100, ($scannedQty / $requiredQty) * 100)) : 0;
            $labels = $qcResis->flatMap(fn ($r) => $r->items)->groupBy('sku')->map(function ($rows, $sku) {
                return sprintf('%s (%d)', $sku, (int) $rows->sum('scanned_qty'));
            })->values();

            return [
                'id'           => $row->id,
                'code'         => $row->code,
                'picker'       => $row->user?->name ?? '-',
                'status'       => $row->status,
                'started_at'   => $started,
                'last_scan_at' => $lastScan,
                'item'         => $labels->implode(', ') ?: '-',
                'qty'          => $scannedQty,
                'note'         => $row->note ?? '',
                'resis'        => $resiList,
                'resi_count'   => $resiList->count(),
                'resi_completed_count' => $completedResi,
                'resi_in_progress_count' => $inProgressResi,
                'required_qty' => $requiredQty,
                'scanned_qty' => $scannedQty,
                'qc_progress' => $qcProgress,
                'items_detail' => [],
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        DB::beginTransaction();
        try {
            $session = QcScanSession::where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            $authUser = $request->user();
            if ($authUser && $authUser->divisi_id !== null && (int) $authUser->divisi_id !== 1) {
                $session->loadMissing('user:id,divisi_id');
                if ((int) $session->user?->divisi_id !== (int) $authUser->divisi_id) {
                    DB::rollBack();
                    return response()->json(['message' => 'Tidak diizinkan'], 403);
                }
            }

            if ($session->status !== 'active') {
                DB::rollBack();
                return response()->json(['message' => 'Sesi tidak aktif dan tidak bisa dihapus'], 422);
            }

            $session->load('resis.items');
            $hasScannedQty = $session->resis->flatMap(fn ($resi) => $resi->items)->sum('scanned_qty') > 0;
            if ($hasScannedQty) {
                DB::rollBack();
                return response()->json(['message' => 'Sesi berisi item, tidak bisa dihapus'], 422);
            }

            $session->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus sesi',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Sesi berhasil dihapus',
        ]);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('started_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('started_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

}
