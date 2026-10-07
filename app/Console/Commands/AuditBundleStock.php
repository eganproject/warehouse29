<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pemeriksaan read-only: mencari data lama yang mencatat stok fisik langsung pada item bundle.
 * Bundle seharusnya hanya punya stok virtual (dihitung dari komponen). Perintah ini tidak
 * mengubah data apa pun; hasilnya dipakai untuk koreksi manual (mis. penyesuaian stok komponen).
 */
class AuditBundleStock extends Command
{
    protected $signature = 'bundle:audit';

    protected $description = 'Cek (read-only) data stok fisik yang tercatat langsung pada item bundle';

    public function handle(): int
    {
        $bundles = DB::table('items')->where('is_bundle', true)->pluck('sku', 'id');
        if ($bundles->isEmpty()) {
            $this->info('Tidak ada item bundle.');

            return self::SUCCESS;
        }

        $ids = $bundles->keys()->all();
        $checks = [
            'Stok fisik (item_stocks) ≠ 0' => DB::table('item_stocks')
                ->whereIn('item_id', $ids)->where('stock', '!=', 0)
                ->get(['item_id', 'stock as detail']),
            'Mutasi stok fisik (stock_mutations)' => DB::table('stock_mutations')
                ->whereIn('item_id', $ids)
                ->select('item_id', 'source_type', DB::raw('COUNT(*) as total'))
                ->groupBy('item_id', 'source_type')->get()
                ->groupBy('item_id')
                ->map(fn ($rows, $itemId) => (object) [
                    'item_id' => $itemId,
                    'detail' => $rows->sum('total').' mutasi, sumber: '.$rows->pluck('source_type')->implode(', '),
                ])->values(),
            'Stok barang rusak (damaged_item_stocks) ≠ 0' => DB::table('damaged_item_stocks')
                ->whereIn('item_id', $ids)
                ->where(fn ($q) => $q->where('stock', '!=', 0)->orWhere('reserved_stock', '!=', 0))
                ->get(['item_id', 'stock', 'reserved_stock'])
                ->map(fn ($row) => (object) [
                    'item_id' => $row->item_id,
                    'detail' => "stok {$row->stock}, reservasi ".(int) $row->reserved_stock,
                ]),
            'Inbound belum selesai berisi bundle' => DB::table('inbound_items as ii')
                ->join('inbound_transactions as it', 'it.id', '=', 'ii.inbound_transaction_id')
                ->whereIn('ii.item_id', $ids)
                ->where(fn ($q) => $q->where(fn ($r) => $r->where('it.type', '!=', 'return')->where('it.status', 'pending'))
                    ->orWhere(fn ($r) => $r->where('it.type', 'return')->where('it.status', 'approved')))
                ->get(['ii.item_id', 'it.code as detail']),
            'Penyesuaian stok pending berisi bundle' => DB::table('stock_adjustment_items as ai')
                ->join('stock_adjustments as a', 'a.id', '=', 'ai.stock_adjustment_id')
                ->whereIn('ai.item_id', $ids)->where('a.status', 'pending')
                ->get(['ai.item_id', 'a.code as detail']),
            'Barang rusak pending berisi bundle' => DB::table('damaged_good_items as di')
                ->join('damaged_goods as d', 'd.id', '=', 'di.damaged_good_id')
                ->whereIn('di.item_id', $ids)->where('d.status', 'pending')
                ->get(['di.item_id', 'd.code as detail']),
            'Stock opname terbuka berisi bundle' => DB::table('stock_opname_items as oi')
                ->join('stock_opnames as o', 'o.id', '=', 'oi.stock_opname_id')
                ->whereIn('oi.item_id', $ids)->where('o.status', 'open')
                ->get(['oi.item_id', 'o.code as detail']),
            'Bundle tanpa komponen' => DB::table('items as i')
                ->whereIn('i.id', $ids)
                ->whereNotExists(fn ($q) => $q->from('item_bundles as b')->whereColumn('b.bundle_item_id', 'i.id'))
                ->pluck('i.id')
                ->map(fn ($id) => (object) ['item_id' => $id, 'detail' => 'tidak bisa discan/dijual']),
        ];

        $issues = 0;
        foreach ($checks as $label => $rows) {
            if ($rows->isEmpty()) {
                $this->line("<info>OK</info>  {$label}");
                continue;
            }

            $issues += $rows->count();
            $this->line("<comment>CEK</comment> {$label}: {$rows->count()} baris");
            $this->table(['SKU bundle', 'Detail'], $rows->map(fn ($row) => [
                $bundles[$row->item_id] ?? "#{$row->item_id}",
                $row->detail,
            ])->all());
        }

        $this->newLine();
        $issues === 0
            ? $this->info('Tidak ditemukan data stok fisik pada item bundle.')
            : $this->warn("Ditemukan {$issues} baris yang perlu ditinjau. Tidak ada data yang diubah.");

        return self::SUCCESS;
    }
}
