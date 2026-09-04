<?php

namespace App\Support;

use App\Models\Item;
use App\Models\PackerScanOut;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\QcScanResi;
use App\Models\QcScanResiItem;
use App\Models\QcTransitItem;
use App\Models\Resi;
use App\Models\ResiCancellation;
use App\Models\StockMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResiCancellationService
{
    public static function cancel(
        int $resiId,
        ?string $reason,
        bool $stockReturnConfirmed,
        ?int $userId
    ): ResiCancellation {
        return DB::transaction(function () use ($resiId, $reason, $stockReturnConfirmed, $userId) {
            $resi = Resi::with('details')->lockForUpdate()->findOrFail($resiId);
            $existingCancellation = ResiCancellation::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();

            if (($resi->status ?? 'active') === 'canceled') {
                if ($existingCancellation && !$existingCancellation->voided_at) {
                    return $existingCancellation;
                }

                throw ValidationException::withMessages([
                    'resi' => 'Resi sudah dibatalkan sebelumnya.',
                ]);
            }

            $uploadDate = $resi->tanggal_upload?->toDateString();
            if ($uploadDate !== now()->toDateString()) {
                throw ValidationException::withMessages([
                    'resi' => 'Cancel hanya dapat dilakukan untuk resi yang di-upload pada tanggal hari berjalan.',
                ]);
            }

            $qcResi = QcScanResi::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();
            $scanOut = PackerScanOut::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();

            $scannedQty = $qcResi
                ? (int) QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)->sum('scanned_qty')
                : 0;
            $requiredQty = $qcResi
                ? (int) QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)->sum('required_qty')
                : 0;
            $stage = self::resolveStage($qcResi, $scanOut, $scannedQty, $requiredQty);
            $reason = trim((string) $reason);

            if ($stage !== 'before_qc' && $reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'Alasan cancel wajib diisi karena resi sudah masuk proses QC.',
                ]);
            }

            if ($stage === 'after_scan_out' && !$stockReturnConfirmed) {
                throw ValidationException::withMessages([
                    'confirm_stock_returned' => 'Konfirmasi bahwa barang sudah kembali secara fisik sebelum stok dikembalikan.',
                ]);
            }

            $canceledAt = now();
            $cancellationPayload = [
                'qc_scan_resi_id' => $qcResi?->id,
                'packer_scan_out_id' => $scanOut?->id,
                'stage' => $stage,
                'reason' => $reason !== '' ? $reason : null,
                'returned_stock_qty' => 0,
                'stock_returned_at' => null,
                'canceled_by' => $userId,
                'canceled_at' => $canceledAt,
                'voided_by' => null,
                'voided_at' => null,
            ];

            if ($existingCancellation) {
                if (!$existingCancellation->voided_at || $existingCancellation->stage !== 'before_qc') {
                    throw ValidationException::withMessages([
                        'resi' => 'Riwayat cancel resi tidak dapat digunakan ulang.',
                    ]);
                }
                $existingCancellation->fill($cancellationPayload)->save();
                $cancellation = $existingCancellation;
            } else {
                $cancellation = ResiCancellation::create([
                    'resi_id' => $resi->id,
                    ...$cancellationPayload,
                ]);
            }

            $returnedStockQty = 0;
            if ($qcResi && $scannedQty > 0) {
                $returnedStockQty = self::reverseQcStock($resi, $qcResi, $cancellation, $userId, $canceledAt);
                self::reverseQcTransit($qcResi, $scanOut);
            }

            self::removePickingDemand($resi);

            $resi->status = 'canceled';
            $resi->canceled_at = $canceledAt;
            $resi->canceled_by = $userId;
            $resi->cancel_reason = $reason !== '' ? $reason : null;
            $resi->uncanceled_at = null;
            $resi->uncanceled_by = null;
            $resi->save();

            $cancellation->returned_stock_qty = $returnedStockQty;
            $cancellation->stock_returned_at = $stage !== 'before_qc' ? $canceledAt : null;
            $cancellation->save();

            return $cancellation->fresh();
        });
    }

    private static function resolveStage(
        ?QcScanResi $qcResi,
        ?PackerScanOut $scanOut,
        int $scannedQty,
        int $requiredQty
    ): string {
        if ($scanOut) {
            return 'after_scan_out';
        }
        if (!$qcResi) {
            return 'before_qc';
        }
        if ($scannedQty > 0 && $requiredQty > $scannedQty) {
            return 'after_partial_qc';
        }

        return 'after_qc';
    }

    private static function reverseQcStock(
        Resi $resi,
        QcScanResi $qcResi,
        ResiCancellation $cancellation,
        ?int $userId,
        \DateTimeInterface $occurredAt
    ): int {
        $mutations = StockMutation::where('source_type', 'qc_resi')
            ->where('source_id', $qcResi->id)
            ->where('direction', 'out')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($mutations->isEmpty()) {
            throw ValidationException::withMessages([
                'resi' => 'Mutasi stok QC tidak ditemukan. Cancel memerlukan pemeriksaan manual agar stok tidak salah.',
            ]);
        }

        $returnedQty = 0;
        $sourceCode = mb_substr((string) ($resi->no_resi ?: $resi->id_pesanan), 0, 50);
        foreach ($mutations as $mutation) {
            StockService::mutate([
                'item_id' => $mutation->item_id,
                'direction' => 'in',
                'qty' => (int) $mutation->qty,
                'source_type' => 'resi_cancellation',
                'source_subtype' => 'reverse_qc',
                'source_id' => $cancellation->id,
                'source_code' => $sourceCode,
                'note' => "Pengembalian stok cancel resi; mutasi QC #{$mutation->id}",
                'occurred_at' => $occurredAt,
                'created_by' => $userId,
                'idempotency_key' => StockService::idempotencyKey([
                    'resi-cancellation',
                    $cancellation->id,
                    'qc-mutation',
                    $mutation->id,
                ]),
            ]);
            $returnedQty += (int) $mutation->qty;
        }

        return $returnedQty;
    }

    private static function reverseQcTransit(QcScanResi $qcResi, ?PackerScanOut $scanOut): void
    {
        $items = QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)
            ->whereNotNull('item_id')
            ->where('scanned_qty', '>', 0)
            ->lockForUpdate()
            ->get(['item_id', 'scanned_qty'])
            ->groupBy('item_id')
            ->map(fn ($rows) => (int) $rows->sum('scanned_qty'));

        foreach ($items as $itemId => $qty) {
            if ($scanOut) {
                self::reverseConsumedTransit((int) $itemId, $qty, $scanOut->scan_date?->toDateString());
            } else {
                self::reverseAvailableTransit((int) $itemId, $qty, $qcResi->scanned_at?->toDateString());
            }
        }
    }

    private static function reverseAvailableTransit(int $itemId, int $qty, ?string $preferredDate): void
    {
        $rows = QcTransitItem::where('item_id', $itemId)
            ->where('remaining_qty', '>', 0)
            ->orderByDesc('transit_date')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        if ($preferredDate) {
            $rows = $rows->sortByDesc(fn ($row) => $row->transit_date?->toDateString() === $preferredDate ? 1 : 0)->values();
        }

        $available = (int) $rows->sum(fn ($row) => min((int) $row->qty, (int) $row->remaining_qty));
        if ($available < $qty) {
            throw ValidationException::withMessages([
                'resi' => "QC transit tidak mencukupi untuk reversal. Dibutuhkan {$qty}, tersedia {$available}.",
            ]);
        }

        $need = $qty;
        foreach ($rows as $row) {
            if ($need <= 0) {
                break;
            }
            $take = min($need, (int) $row->qty, (int) $row->remaining_qty);
            if ($take <= 0) {
                continue;
            }
            $row->qty -= $take;
            $row->remaining_qty -= $take;
            $need -= $take;
            self::saveOrDeleteTransit($row);
        }
    }

    private static function reverseConsumedTransit(int $itemId, int $qty, ?string $scanDate): void
    {
        $rows = QcTransitItem::where('item_id', $itemId)
            ->when($scanDate, fn ($query) => $query->whereDate('transit_date', '<=', $scanDate))
            ->whereColumn('qty', '>', 'remaining_qty')
            ->orderBy('transit_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $consumed = (int) $rows->sum(fn ($row) => max(0, (int) $row->qty - (int) $row->remaining_qty));
        if ($consumed < $qty) {
            throw ValidationException::withMessages([
                'resi' => "Riwayat konsumsi QC transit tidak mencukupi untuk reversal scan out. Dibutuhkan {$qty}, tersedia {$consumed}.",
            ]);
        }

        $need = $qty;
        foreach ($rows as $row) {
            if ($need <= 0) {
                break;
            }
            $take = min($need, max(0, (int) $row->qty - (int) $row->remaining_qty));
            if ($take <= 0) {
                continue;
            }
            $row->qty -= $take;
            $need -= $take;
            self::saveOrDeleteTransit($row);
        }
    }

    private static function saveOrDeleteTransit(QcTransitItem $row): void
    {
        if ((int) $row->qty <= 0 && (int) $row->remaining_qty <= 0) {
            $row->delete();
            return;
        }

        $row->save();
    }

    private static function removePickingDemand(Resi $resi): void
    {
        $date = $resi->tanggal_upload?->toDateString() ?? now()->toDateString();
        $grouped = DB::table('resi_details')
            ->where('resi_id', $resi->id)
            ->where('qty', '>', 0)
            ->select('sku', DB::raw('SUM(qty) as qty'))
            ->groupBy('sku')
            ->get();

        foreach ($grouped as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            $qty = (int) ($row->qty ?? 0);
            if ($sku === '' || $qty <= 0) {
                continue;
            }
            self::adjustPickingList($date, $sku, -$qty);
        }
    }

    private static function adjustPickingList(string $date, string $sku, int $delta): void
    {
        $row = PickingList::whereDate('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();
        $newQty = $row ? max(0, (int) $row->qty + $delta) : 0;
        $pickedQty = self::getPickedQty($date, $sku);
        $remaining = max(0, $newQty - $pickedQty);
        $exceptionQty = max(0, $pickedQty - $newQty);

        if ($row) {
            $row->qty = $newQty;
            $row->remaining_qty = $remaining;
            if ($newQty <= 0 && $remaining <= 0) {
                $row->delete();
            } else {
                $row->save();
            }
        }

        $exception = PickingListException::whereDate('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();
        if ($exceptionQty > 0) {
            if ($exception) {
                $exception->qty = $exceptionQty;
                $exception->save();
            } else {
                PickingListException::create([
                    'list_date' => $date,
                    'sku' => $sku,
                    'qty' => $exceptionQty,
                ]);
            }
        } elseif ($exception) {
            $exception->delete();
        }
    }

    private static function getPickedQty(string $date, string $sku): int
    {
        $itemId = Item::where('sku', $sku)->value('id');
        if (!$itemId) {
            return 0;
        }

        return (int) QcTransitItem::where('item_id', $itemId)
            ->whereDate('transit_date', $date)
            ->value('qty');
    }
}
