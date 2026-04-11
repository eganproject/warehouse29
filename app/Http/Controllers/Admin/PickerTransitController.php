<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\PackerTransitStatusExport;
use App\Exports\PickerTransitStatusExport;
use App\Models\PackerTransitHistory;
use App\Models\PackerScanException;
use App\Models\PickerTransitItem;
use App\Models\Resi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PickerTransitController extends Controller
{
    public function index()
    {
        return view('admin.inventory.picker-transit.index', [
            'dataUrl' => route('admin.inventory.picker-transit.data'),
            'dataUrlPacker' => route('admin.inventory.picker-transit.packer-data'),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = PickerTransitItem::query()
            ->with('item')
            ->orderBy('picked_date', 'desc')
            ->orderBy('id', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->whereHas('item', function ($itemQ) use ($search) {
                $itemQ->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $this->applyDateFilter($baseQuery, $request);

        $recordsTotal = PickerTransitItem::count();
        $summaryQuery = clone $baseQuery;
        $summary = [
            'ongoing' => (clone $summaryQuery)->where('remaining_qty', '>', 0)->count(),
            'done' => (clone $summaryQuery)->where('remaining_qty', '<=', 0)->count(),
        ];

        $query = clone $baseQuery;
        $this->applyPickerStatusFilter($query, $request);
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $item = $row->item;
            return [
                'date' => $row->picked_date?->format('Y-m-d') ?? '-',
                'sku' => $item?->sku ?? '-',
                'name' => $item?->name ?? '-',
                'qty' => (int) $row->qty,
                'remaining_qty' => (int) $row->remaining_qty,
                'picked_at' => $row->picked_at?->format('Y-m-d H:i') ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function pickerDetail(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'sku' => ['required', 'string', 'max:100'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $sku = trim((string) $validated['sku']);

        $rows = Resi::query()
            ->join('resi_details as rd', 'rd.resi_id', '=', 'resis.id')
            ->whereDate('resis.tanggal_upload', $date)
            ->where('rd.sku', $sku)
            ->where(function ($q) {
                $q->whereNull('resis.status')
                    ->orWhere('resis.status', '!=', 'canceled');
            })
            ->select([
                'resis.id_pesanan',
                'resis.no_resi',
                DB::raw('SUM(rd.qty) as qty'),
            ])
            ->groupBy('resis.id_pesanan', 'resis.no_resi')
            ->orderBy('resis.no_resi')
            ->limit(1000)
            ->get()
            ->map(function ($row) {
                return [
                    'id_pesanan' => $row->id_pesanan ?? '-',
                    'no_resi' => $row->no_resi ?? '-',
                    'qty' => (int) ($row->qty ?? 0),
                ];
            })
            ->values();

        $totalQty = (int) $rows->sum('qty');

        return response()->json([
            'meta' => [
                'date' => $date,
                'sku' => $sku,
                'total_resi' => (int) $rows->count(),
                'total_qty' => $totalQty,
            ],
            'data' => $rows,
        ]);
    }

    public function recalculateToday(Request $request)
    {
        if ((auth()->user()->email ?? '') !== 'admin@gmail.com') {
            abort(403);
        }

        $date = now()->toDateString();

        DB::beginTransaction();
        try {
            $consumedRows = DB::table('packer_resi_scans as prs')
                ->join('resis as r', 'r.id', '=', 'prs.resi_id')
                ->join('resi_details as rd', 'rd.resi_id', '=', 'r.id')
                ->join('items as i', 'i.sku', '=', 'rd.sku')
                ->where('prs.scan_date', $date)
                ->where(function ($q) {
                    $q->whereNull('r.status')
                        ->orWhere('r.status', '!=', 'canceled');
                })
                ->select([
                    'i.id as item_id',
                    DB::raw('SUM(rd.qty) as qty'),
                ])
                ->groupBy('i.id')
                ->get();

            $consumedByItemId = [];
            foreach ($consumedRows as $row) {
                $itemId = (int) ($row->item_id ?? 0);
                if ($itemId <= 0) {
                    continue;
                }
                $consumedByItemId[$itemId] = (int) ($row->qty ?? 0);
            }

            $pickedRows = PickerTransitItem::query()
                ->where('picked_date', $date)
                ->lockForUpdate()
                ->get(['id', 'item_id', 'qty', 'remaining_qty']);

            $updated = 0;
            foreach ($pickedRows as $row) {
                $pickedQty = (int) $row->qty;
                $consumedQty = (int) ($consumedByItemId[(int) $row->item_id] ?? 0);
                $newRemaining = $pickedQty - $consumedQty;
                if ($newRemaining < 0) {
                    $newRemaining = 0;
                }
                if ($newRemaining > $pickedQty) {
                    $newRemaining = $pickedQty;
                }

                if ((int) $row->remaining_qty !== $newRemaining) {
                    $row->remaining_qty = $newRemaining;
                    $row->save();
                    $updated++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghitung ulang transit hari ini.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Recalculate transit hari ini berhasil.',
            'meta' => [
                'date' => $date,
                'updated_rows' => $updated,
            ],
        ]);
    }

    public function auditPickerRemaining(Request $request)
    {
        if ((auth()->user()->email ?? '') !== 'admin@gmail.com') {
            abort(403);
        }

        $date = $request->query('date') ?: now()->toDateString();
        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            $date = now()->toDateString();
        }

        $transitRows = DB::table('picker_transit_items as pti')
            ->join('items as i', 'i.id', '=', 'pti.item_id')
            ->where('pti.picked_date', $date)
            ->where('pti.remaining_qty', '>', 0)
            ->select([
                'pti.item_id',
                'i.sku',
                'i.name',
                'pti.qty as picked_qty',
                'pti.remaining_qty',
            ])
            ->orderByDesc('pti.remaining_qty')
            ->limit(300)
            ->get();

        if ($transitRows->isEmpty()) {
            return response()->json([
                'meta' => [
                    'date' => $date,
                    'total' => 0,
                ],
                'data' => [],
            ]);
        }

        $skus = $transitRows->pluck('sku')->filter()->values()->all();

        $exceptionSkus = PackerScanException::query()
            ->whereIn('sku', $skus)
            ->pluck('sku')
            ->map(fn ($sku) => strtolower(trim((string) $sku)))
            ->filter()
            ->values()
            ->all();
        $exceptionLookup = array_flip($exceptionSkus);

        // Qty packed based on resi upload date (regardless of when the scan happened)
        $packedByUpload = DB::table('resis as r')
            ->join('packer_resi_scans as prs', 'prs.resi_id', '=', 'r.id')
            ->join('resi_details as rd', 'rd.resi_id', '=', 'r.id')
            ->whereDate('r.tanggal_upload', $date)
            ->whereIn('rd.sku', $skus)
            ->where(function ($q) {
                $q->whereNull('r.status')
                    ->orWhere('r.status', '!=', 'canceled');
            })
            ->select([
                'rd.sku',
                DB::raw('SUM(rd.qty) as qty'),
            ])
            ->groupBy('rd.sku')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) ($row->sku ?? '') => (int) ($row->qty ?? 0)])
            ->all();

        // Qty packed scanned on this date
        $packedByScanDate = DB::table('packer_resi_scans as prs')
            ->join('resis as r', 'r.id', '=', 'prs.resi_id')
            ->join('resi_details as rd', 'rd.resi_id', '=', 'r.id')
            ->where('prs.scan_date', $date)
            ->whereIn('rd.sku', $skus)
            ->where(function ($q) {
                $q->whereNull('r.status')
                    ->orWhere('r.status', '!=', 'canceled');
            })
            ->select([
                'rd.sku',
                DB::raw('SUM(rd.qty) as qty'),
            ])
            ->groupBy('rd.sku')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) ($row->sku ?? '') => (int) ($row->qty ?? 0)])
            ->all();

        $data = $transitRows->map(function ($row) use ($packedByUpload, $packedByScanDate, $exceptionLookup) {
            $sku = (string) ($row->sku ?? '');
            $skuKey = strtolower(trim($sku));
            $pickedQty = (int) ($row->picked_qty ?? 0);
            $remainingQty = (int) ($row->remaining_qty ?? 0);
            $packedUploadQty = (int) ($packedByUpload[$sku] ?? 0);
            $packedScanQty = (int) ($packedByScanDate[$sku] ?? 0);
            $isException = $skuKey !== '' && isset($exceptionLookup[$skuKey]);

            $suspect = 'Belum semua qty scanned packing';
            if ($isException) {
                $suspect = 'SKU ada di packer scan exception (di packer scan tidak mengurangi transit)';
            } elseif ($packedUploadQty >= $pickedQty && $remainingQty > 0) {
                $suspect = 'Semua resi batch tanggal ini sudah scanned, tapi remaining masih ada (mismatch alokasi tanggal/row transit)';
            } elseif ($packedScanQty >= $pickedQty && $remainingQty > 0) {
                $suspect = 'Scan hari ini sudah cukup, tapi remaining masih ada (kemungkinan transit tidak ter-reduce untuk sebagian SKU)';
            }

            return [
                'sku' => $sku !== '' ? $sku : '-',
                'name' => (string) ($row->name ?? '-'),
                'picked_qty' => $pickedQty,
                'remaining_qty' => $remainingQty,
                'packed_qty_by_upload_date' => $packedUploadQty,
                'packed_qty_by_scan_date' => $packedScanQty,
                'is_exception' => $isException ? 1 : 0,
                'suspect' => $suspect,
            ];
        })->values();

        return response()->json([
            'meta' => [
                'date' => $date,
                'total' => (int) $data->count(),
            ],
            'data' => $data,
        ]);
    }

    public function dataPacker(Request $request)
    {
        $baseQuery = PackerTransitHistory::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('id_pesanan', 'like', "%{$search}%")
                    ->orWhere('no_resi', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $this->applyPackerDateFilter($baseQuery, $request);

        $recordsTotal = PackerTransitHistory::count();
        $summaryQuery = clone $baseQuery;
        $summary = [
            'pending' => (clone $summaryQuery)->where('status', 'menunggu scan out')->count(),
            'done' => (clone $summaryQuery)->where('status', 'selesai')->count(),
        ];

        $query = clone $baseQuery;
        $this->applyPackerStatusFilter($query, $request);
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            return [
                'created_at' => $row->created_at?->format('Y-m-d H:i') ?? '-',
                'id_pesanan' => $row->id_pesanan ?? '-',
                'no_resi' => $row->no_resi ?? '-',
                'status' => $row->status ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function exportPickerStatus(Request $request)
    {
        $filters = [
            'q' => $request->input('q', ''),
            'date' => $request->input('date'),
            'status' => $request->input('status', ''),
        ];

        $date = $filters['date'] ?: now()->toDateString();
        $suffix = $filters['status'] ?: 'all';
        $filename = "picker-transit-{$date}-{$suffix}.xlsx";

        return Excel::download(new PickerTransitStatusExport($filters), $filename);
    }

    public function exportPackerStatus(Request $request)
    {
        $filters = [
            'q' => $request->input('q', ''),
            'date' => $request->input('date'),
            'status' => $request->input('status', ''),
        ];

        $date = $filters['date'] ?: now()->toDateString();
        $suffix = $filters['status'] ?: 'all';
        $filename = "packer-transit-{$date}-{$suffix}.xlsx";

        return Excel::download(new PackerTransitStatusExport($filters), $filename);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $date = $request->input('date') ?: now()->toDateString();

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->where('picked_date', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function applyPackerDateFilter($query, Request $request): void
    {
        $date = $request->input('date') ?: now()->toDateString();

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->whereDate('created_at', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function applyPickerStatusFilter($query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if ($status === 'ongoing') {
            $query->where('remaining_qty', '>', 0);
        } elseif ($status === 'done') {
            $query->where('remaining_qty', '<=', 0);
        }
    }

    private function applyPackerStatusFilter($query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if ($status === 'pending') {
            $query->where('status', 'menunggu scan out');
        } elseif ($status === 'done') {
            $query->where('status', 'selesai');
        }
    }
}
