<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PackerResiScan;
use App\Models\PackerScanException;
use App\Models\PackerTransitHistory;
use App\Models\PickerTransitItem;
use App\Models\Resi;
use App\Models\ResiDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackerScanController extends Controller
{
    public function index()
    {
        return view('picker.packer-scan', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'scan' => route('picker.packer.scan'),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:id_pesanan,no_resi'],
            'code' => ['required', 'string'],
        ]);

        $type = $validated['type'];
        $code = trim((string) $validated['code']);
        if ($code === '') {
            return response()->json([
                'message' => 'Kode resi tidak boleh kosong.',
            ], 422);
        }

        $resiQuery = Resi::query();
        if ($type === 'no_resi') {
            $resiQuery->where('no_resi', $code);
        } else {
            $resiQuery->where('id_pesanan', $code);
        }

        $resi = $resiQuery->first();

        if (!$resi) {
            return response()->json([
                'message' => 'Resi tidak ditemukan.',
            ], 422);
        }
        if (($resi->status ?? 'active') === 'canceled') {
            return response()->json([
                'message' => 'Resi sudah dibatalkan.',
            ], 422);
        }

        $details = ResiDetail::where('resi_id', $resi->id)
            ->get(['sku', 'qty']);

        if ($details->isEmpty()) {
            return response()->json([
                'message' => 'Detail resi belum tersedia.',
            ], 422);
        }

        $exceptionSkus = PackerScanException::query()
            ->pluck('sku')
            ->map(fn ($sku) => strtolower(trim((string) $sku)))
            ->filter()
            ->values()
            ->all();
        $exceptionLookup = array_flip($exceptionSkus);

        $skuTotals = [];
        $excludedTotals = [];
        foreach ($details as $detail) {
            $sku = trim((string) $detail->sku);
            $qty = (int) $detail->qty;
            if ($sku === '' || $qty <= 0) {
                continue;
            }
            $skuKey = strtolower($sku);
            if (isset($exceptionLookup[$skuKey])) {
                $excludedTotals[$sku] = ($excludedTotals[$sku] ?? 0) + $qty;
                continue;
            }
            $skuTotals[$sku] = ($skuTotals[$sku] ?? 0) + $qty;
        }

        if (empty($skuTotals) && empty($excludedTotals)) {
            return response()->json([
                'message' => 'Detail resi tidak valid.',
            ], 422);
        }

        $scanDate = now()->toDateString();

        DB::beginTransaction();
        try {
            $existingScan = PackerResiScan::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();

            if ($existingScan) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi sudah pernah discan.',
                ], 422);
            }

            $items = collect();
            if (!empty($skuTotals)) {
                $items = Item::whereIn('sku', array_keys($skuTotals))
                    ->get(['id', 'sku', 'name'])
                    ->keyBy('sku');
            }

            $issues = [];
            $updates = [];

            foreach ($skuTotals as $sku => $qty) {
                $item = $items->get($sku);
                if (!$item) {
                    $issues[] = [
                        'sku' => $sku,
                        'required' => $qty,
                        'reason' => 'SKU tidak ditemukan',
                    ];
                    continue;
                }

                $transitRow = PickerTransitItem::where('item_id', $item->id)
                    ->where('picked_date', '<=', $scanDate)
                    ->where('remaining_qty', '>', 0)
                    ->orderByDesc('picked_date')
                    ->lockForUpdate()
                    ->first();

                if (!$transitRow) {
                    $issues[] = [
                        'sku' => $sku,
                        'required' => $qty,
                        'reason' => 'Transit hari ini belum tersedia',
                    ];
                    continue;
                }

                $remaining = (int) $transitRow->remaining_qty;
                if ($remaining < $qty) {
                    $issues[] = [
                        'sku' => $sku,
                        'required' => $qty,
                        'available' => $remaining,
                        'reason' => 'Sisa transit tidak mencukupi',
                    ];
                    continue;
                }

                $updates[] = [
                    'row' => $transitRow,
                    'qty' => $qty,
                ];
            }

            if (!empty($issues)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Masih ada SKU dengan sisa transit tidak mencukupi.',
                    'details' => $issues,
                ], 422);
            }

            foreach ($updates as $update) {
                $row = $update['row'];
                $row->remaining_qty = max(0, (int) $row->remaining_qty - (int) $update['qty']);
                $row->save();
            }

            PackerResiScan::create([
                'resi_id' => $resi->id,
                'scan_type' => $type,
                'scan_code' => $code,
                'scan_date' => $scanDate,
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
            ]);

            $transitHistory = PackerTransitHistory::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();
            if (!$transitHistory) {
                PackerTransitHistory::create([
                    'resi_id' => $resi->id,
                    'id_pesanan' => $resi->id_pesanan,
                    'no_resi' => $resi->no_resi,
                    'status' => 'menunggu scan out',
                ]);
            } elseif (empty($transitHistory->no_resi) && !empty($resi->no_resi)) {
                $transitHistory->no_resi = $resi->no_resi;
                $transitHistory->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memproses resi.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $itemsPayload = [];
        foreach ($skuTotals as $sku => $qty) {
            $itemsPayload[] = [
                'sku' => $sku,
                'qty' => $qty,
                'excluded' => false,
            ];
        }
        foreach ($excludedTotals as $sku => $qty) {
            $itemsPayload[] = [
                'sku' => $sku,
                'qty' => $qty,
                'excluded' => true,
            ];
        }

        return response()->json([
            'message' => 'Resi berhasil diproses.',
            'resi' => [
                'id_pesanan' => $resi->id_pesanan,
                'no_resi' => $resi->no_resi,
                'tanggal_pesanan' => $resi->tanggal_pesanan?->format('Y-m-d'),
            ],
            'items' => $itemsPayload,
        ]);
    }
}
