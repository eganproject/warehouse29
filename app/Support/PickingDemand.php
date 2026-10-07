<?php

namespace App\Support;

use App\Models\Item;
use App\Models\PackerScanException;
use App\Models\QcTransitItem;
use Illuminate\Support\Facades\DB;

/**
 * Picking list berisi barang fisik. SKU bundle pada resi diterjemahkan ke komponennya
 * (BNDL01 x3 -> TRIP1 x30) dan digabung dengan SKU yang sama yang dipesan langsung.
 *
 * Transit QC tetap dicatat per SKU resi (bundle) karena dipakai scan out, jadi qty
 * "sudah diambil" untuk komponen dihitung dari progres scan komponen QC bundle.
 */
class PickingDemand
{
    /**
     * @param iterable $items baris dengan sku & qty (array atau object)
     * @return array<string,int> [sku fisik => qty]
     */
    public static function physicalTotals(iterable $items): array
    {
        $bySku = [];
        foreach ($items as $row) {
            $sku = trim((string) (is_array($row) ? ($row['sku'] ?? '') : ($row->sku ?? '')));
            $qty = (int) (is_array($row) ? ($row['qty'] ?? 0) : ($row->qty ?? 0));
            if ($sku !== '' && $qty > 0) {
                $bySku[$sku] = ($bySku[$sku] ?? 0) + $qty;
            }
        }
        if (empty($bySku)) {
            return [];
        }

        $bundles = Item::active()
            ->where('is_bundle', true)
            ->whereIn('sku', array_keys($bySku))
            ->get(['id', 'sku'])
            ->keyBy(fn ($item) => strtolower($item->sku));
        if ($bundles->isEmpty()) {
            return $bySku;
        }

        $composition = BundleService::compositionFor($bundles->pluck('id')->all());
        $exceptions = self::packerExceptionLookup();

        $totals = [];
        foreach ($bySku as $sku => $qty) {
            $bundle = $bundles->get(strtolower($sku));
            $components = $bundle ? ($composition[$bundle->id]['components'] ?? []) : [];

            if (!$bundle || empty($components)) {
                // Item biasa, atau bundle tanpa komponen (dibiarkan terlihat agar bisa diperbaiki).
                $totals[$sku] = ($totals[$sku] ?? 0) + $qty;
                continue;
            }
            if (isset($exceptions[strtolower($sku)])) {
                // Bundle yang dikecualikan dari QC tidak perlu diambil untuk QC.
                continue;
            }

            foreach ($components as $component) {
                $totals[$component['sku']] = ($totals[$component['sku']] ?? 0) + $qty * (int) $component['qty'];
            }
        }

        return $totals;
    }

    /** Qty barang fisik yang sudah diambil (QC) untuk SKU pada tanggal picking list. */
    public static function pickedQty(string $date, string $sku): int
    {
        $item = Item::where('sku', $sku)->first(['id', 'is_bundle']);
        if (!$item) {
            return 0;
        }

        $direct = (int) QcTransitItem::where('item_id', $item->id)
            ->whereDate('transit_date', $date)
            ->value('qty');
        if ($item->is_bundle) {
            return $direct;
        }

        return $direct + (int) self::componentPickedQuery($date)
            ->where('c.component_item_id', $item->id)
            ->sum('c.scanned_qty');
    }

    /** @return array<string,int> [sku fisik => qty sudah diambil] */
    public static function pickedTotals(string $date): array
    {
        $picked = [];

        $direct = DB::table('qc_transit_items as t')
            ->join('items as i', 'i.id', '=', 't.item_id')
            ->whereDate('t.transit_date', $date)
            // Transit bundle sudah tercermin lewat komponennya.
            ->where('i.is_bundle', false)
            ->groupBy('i.sku')
            ->get(['i.sku', DB::raw('SUM(t.qty) as qty')]);
        foreach ($direct as $row) {
            $picked[$row->sku] = ($picked[$row->sku] ?? 0) + (int) $row->qty;
        }

        $components = self::componentPickedQuery($date)
            ->join('items as ci', 'ci.id', '=', 'c.component_item_id')
            ->groupBy('ci.sku')
            ->get(['ci.sku', DB::raw('SUM(c.scanned_qty) as qty')]);
        foreach ($components as $row) {
            $picked[$row->sku] = ($picked[$row->sku] ?? 0) + (int) $row->qty;
        }

        return array_filter($picked, fn ($qty) => $qty > 0);
    }

    /**
     * Asal kebutuhan dari bundle per SKU fisik, untuk info picker.
     *
     * @return array<string, array<int, array{bundle_sku: string, bundle_qty: int, qty: int}>>
     */
    public static function bundleSources(string $date): array
    {
        $rows = DB::table('resi_details as rd')
            ->join('resis as r', 'r.id', '=', 'rd.resi_id')
            ->join('items as i', 'i.sku', '=', 'rd.sku')
            ->whereDate('r.tanggal_upload', $date)
            ->where(fn ($q) => $q->whereNull('r.status')->orWhere('r.status', '!=', 'canceled'))
            ->where('i.is_bundle', true)
            ->where('i.is_active', true)
            ->groupBy('i.id', 'i.sku')
            ->get(['i.id', 'i.sku', DB::raw('SUM(rd.qty) as qty')]);
        if ($rows->isEmpty()) {
            return [];
        }

        $composition = BundleService::compositionFor($rows->pluck('id')->all());
        $exceptions = self::packerExceptionLookup();
        $sources = [];
        foreach ($rows as $row) {
            if (isset($exceptions[strtolower($row->sku)])) {
                continue;
            }
            foreach ($composition[$row->id]['components'] ?? [] as $component) {
                $sources[$component['sku']][] = [
                    'bundle_sku' => $row->sku,
                    'bundle_qty' => (int) $row->qty,
                    'qty' => (int) $row->qty * (int) $component['qty'],
                ];
            }
        }

        return $sources;
    }

    public static function sourcesLabel(array $sources): ?string
    {
        if (empty($sources)) {
            return null;
        }

        return collect($sources)
            ->map(fn ($row) => "{$row['bundle_sku']} x{$row['bundle_qty']} = {$row['qty']}")
            ->implode(', ');
    }

    private static function componentPickedQuery(string $date)
    {
        return DB::table('qc_scan_resi_bundle_components as c')
            ->join('qc_scan_resi_items as qi', 'qi.id', '=', 'c.qc_scan_resi_item_id')
            ->join('qc_scan_resis as q', 'q.id', '=', 'qi.qc_scan_resi_id')
            ->join('resis as r', 'r.id', '=', 'q.resi_id')
            ->whereDate('q.scanned_at', $date)
            // Resi batal: stok & transitnya sudah dibalik, jadi tidak dihitung sebagai diambil.
            ->where(fn ($q) => $q->whereNull('r.status')->orWhere('r.status', '!=', 'canceled'));
    }

    private static function packerExceptionLookup(): array
    {
        return PackerScanException::query()
            ->pluck('sku')
            ->map(fn ($sku) => strtolower(trim((string) $sku)))
            ->filter()
            ->flip()
            ->all();
    }
}
