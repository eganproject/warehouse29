<?php

namespace App\Support;

use App\Models\Item;
use App\Models\PackerScanOut;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\QcScanResi;
use App\Models\QcScanResiBundleComponent;
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
            // Komponen bundle yang sudah discan (stoknya sudah terpotong) walau bundle belum lengkap.
            $componentScannedQty = $qcResi
                ? (int) QcScanResiBundleComponent::whereIn(
                    'qc_scan_resi_item_id',
                    QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)->select('id')
                )->sum('scanned_qty')
                : 0;
            $stage = self::resolveStage($qcResi, $scanOut, $scannedQty, $requiredQty, $componentScannedQty);
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
            if ($qcResi && ($scannedQty > 0 || $componentScannedQty > 0)) {
                $returnedStockQty = self::reverseQcStock($resi, $qcResi, $cancellation, $userId, $canceledAt);
                self::reverseQcTransit($qcResi, $scanOut);
            }

            $resi->status = 'canceled';
            $resi->canceled_at = $canceledAt;
            $resi->canceled_by = $userId;
            $resi->cancel_reason = $reason !== '' ? $reason : null;
            $resi->uncanceled_at = null;
            $resi->uncanceled_by = null;
            $resi->save();

            // Setelah status batal tersimpan, agar komponen bundle resi ini tidak lagi
            // terhitung "sudah diambil" saat sisa picking list dihitung ulang.
            self::removePickingDemand($resi);

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
        int $requiredQty,
        int $componentScannedQty = 0
    ): string {
        if ($scanOut) {
            return 'after_scan_out';
        }
        if (!$qcResi) {
            return 'before_qc';
        }
        if (($scannedQty > 0 || $componentScannedQty > 0) && $requiredQty > $scannedQty) {
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

        $qcDate = $qcResi->scanned_at?->toDateString();
        foreach ($items as $itemId => $qty) {
            self::reverseTransit((int) $itemId, $qty, $qcDate, $scanOut?->scan_date?->toDateString(), $scanOut !== null);
        }
    }

    /**
     * Transit QC per (item, tanggal): qty = total yang di-QC pada tanggal itu, remaining = sisa yang
     * belum discan out. Scan out memakai remaining FIFO lintas tanggal tanpa mencatat baris mana yang
     * dipakai resi, jadi pembalikan cancel:
     * - selalu mengurangi qty pada tanggal QC resi (tempat qty itu dulu ditambahkan), dan
     * - mengurangi total remaining (belum scan out) atau mempertahankannya (sudah scan out),
     *   dengan menyeimbangkan remaining pada tanggal lain bila baris tanggal QC tidak cukup.
     */
    private static function reverseTransit(int $itemId, int $qty, ?string $qcDate, ?string $scanDate, bool $scannedOut): void
    {
        $rows = QcTransitItem::where('item_id', $itemId)
            ->orderBy('transit_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($scannedOut) {
            $consumed = (int) $rows->sum(fn ($row) => max(0, (int) $row->qty - (int) $row->remaining_qty));
            if ($consumed < $qty) {
                throw ValidationException::withMessages([
                    'resi' => "Riwayat konsumsi QC transit tidak mencukupi untuk reversal scan out. Dibutuhkan {$qty}, tersedia {$consumed}.",
                ]);
            }
        } else {
            $available = (int) $rows->sum(fn ($row) => min((int) $row->qty, (int) $row->remaining_qty));
            if ($available < $qty) {
                throw ValidationException::withMessages([
                    'resi' => "QC transit tidak mencukupi untuk reversal. Dibutuhkan {$qty}, tersedia {$available}.",
                ]);
            }
        }

        $home = $qcDate ? $rows->first(fn ($row) => $row->transit_date?->toDateString() === $qcDate) : null;
        $others = $rows->reject(fn ($row) => $home && (int) $row->id === (int) $home->id);
        $qtyLeft = $qty;
        // Perubahan total remaining yang masih harus diterapkan pada baris lain.
        $remainingDelta = $scannedOut ? 0 : -$qty;

        if ($home) {
            $take = min($qtyLeft, (int) $home->qty);
            $before = (int) $home->remaining_qty;
            $home->qty -= $take;
            $after = $scannedOut ? $before : max(0, $before - $qty);
            $home->remaining_qty = min($after, (int) $home->qty);
            $remainingDelta -= (int) $home->remaining_qty - $before;
            $qtyLeft -= $take;
        }

        // Data lama: qty tanggal QC tidak cukup, sisanya diambil dari tanggal lain seperti sebelumnya.
        if ($qtyLeft > 0) {
            $fallback = $scannedOut
                ? $others->filter(fn ($row) => !$scanDate || $row->transit_date?->toDateString() <= $scanDate)
                : $others->reverse();
            foreach ($fallback as $row) {
                if ($qtyLeft <= 0) {
                    break;
                }
                $capacity = $scannedOut
                    ? max(0, (int) $row->qty - (int) $row->remaining_qty)
                    : min((int) $row->qty, (int) $row->remaining_qty);
                $take = min($qtyLeft, $capacity);
                $row->qty -= $take;
                if (!$scannedOut) {
                    $row->remaining_qty -= $take;
                    $remainingDelta += $take;
                }
                $qtyLeft -= $take;
            }
        }

        foreach ($others->reverse() as $row) {
            if ($remainingDelta === 0) {
                break;
            }
            if ($remainingDelta < 0) {
                $take = min(-$remainingDelta, (int) $row->remaining_qty);
                $row->remaining_qty -= $take;
                $remainingDelta += $take;
            } else {
                $take = min($remainingDelta, max(0, (int) $row->qty - (int) $row->remaining_qty));
                $row->remaining_qty += $take;
                $remainingDelta -= $take;
            }
        }

        if ($qtyLeft > 0 || $remainingDelta !== 0) {
            throw ValidationException::withMessages([
                'resi' => 'Data QC transit tidak konsisten untuk reversal. Cancel memerlukan pemeriksaan manual.',
            ]);
        }

        foreach ($rows as $row) {
            if ($row->isDirty()) {
                self::saveOrDeleteTransit($row);
            }
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
        $details = DB::table('resi_details')
            ->where('resi_id', $resi->id)
            ->where('qty', '>', 0)
            ->get(['sku', 'qty']);

        // Picking list berisi barang fisik: SKU bundle diterjemahkan ke komponennya.
        foreach (PickingDemand::physicalTotals($details) as $sku => $qty) {
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
        return PickingDemand::pickedQty($date, $sku);
    }
}
