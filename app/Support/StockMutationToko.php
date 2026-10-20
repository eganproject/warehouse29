<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hubungkan mutasi stok ke toko lewat resi sumbernya.
 *
 * - qc_resi           : source_id = qc_scan_resis.id (barang keluar karena QC resi)
 * - resi_cancellation : source_id = resi_cancellations.id (stok kembali karena resi batal)
 * - inbound / inbound_return : source_id = inbound_transactions.id (retur yang terhubung resi)
 *
 * Mutasi lain (penerimaan, opname, penyesuaian, outbound manual) tidak punya toko.
 */
class StockMutationToko
{
    /**
     * Tambahkan left join sampai tabel resis (alias smt_resi) dan tokos (alias smt_toko).
     */
    public static function join(Builder $query, string $alias): Builder
    {
        return $query
            ->leftJoin('qc_scan_resis as smt_qc', function ($join) use ($alias) {
                $join->on('smt_qc.id', '=', "{$alias}.source_id")->where("{$alias}.source_type", '=', 'qc_resi');
            })
            ->leftJoin('resi_cancellations as smt_rc', function ($join) use ($alias) {
                $join->on('smt_rc.id', '=', "{$alias}.source_id")->where("{$alias}.source_type", '=', 'resi_cancellation');
            })
            ->leftJoin('inbound_transactions as smt_in', function ($join) use ($alias) {
                $join->on('smt_in.id', '=', "{$alias}.source_id")->whereIn("{$alias}.source_type", ['inbound', 'inbound_return']);
            })
            ->leftJoin('resis as smt_resi', 'smt_resi.id', '=', DB::raw('COALESCE(smt_qc.resi_id, smt_rc.resi_id, smt_in.resi_id)'))
            ->leftJoin('tokos as smt_toko', 'smt_toko.id', '=', 'smt_resi.toko_id');
    }

    /**
     * Kondisi SQL (tanpa binding) untuk mencocokkan toko pada query yang sudah di-join.
     * 'none' = mutasi tanpa toko (bukan dari resi, atau resi tanpa toko).
     */
    public static function condition(int|string $tokoId): string
    {
        return $tokoId === TokoFilter::NONE
            ? 'smt_resi.toko_id IS NULL'
            : 'smt_resi.toko_id = '.(int) $tokoId;
    }

    /**
     * Batasi query mutasi (Eloquent/Query Builder, tanpa join) ke toko tertentu lewat subquery id.
     */
    public static function applyFilter($query, string $table, string $idColumn, int|string|null $tokoId): void
    {
        if ($tokoId === null) {
            return;
        }

        $query->whereIn($idColumn, function ($sub) use ($table, $tokoId) {
            $sub->from($table.' as smt_m')->select('smt_m.id');
            self::join($sub, 'smt_m');
            $sub->whereRaw(self::condition($tokoId));
        });
    }

    /**
     * Nama toko & channel per id mutasi untuk satu halaman data.
     *
     * @return Collection<int,object{toko:?string,channel:?string}>
     */
    public static function namesFor(string $table, iterable $mutationIds): Collection
    {
        $ids = collect($mutationIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $query = DB::table($table.' as smt_m')->whereIn('smt_m.id', $ids);
        self::join($query, 'smt_m');

        return $query
            ->leftJoin('channels as smt_channel', 'smt_channel.id', '=', 'smt_resi.channel_id')
            ->whereNotNull('smt_resi.id')
            ->get(['smt_m.id', 'smt_toko.name as toko', 'smt_channel.name as channel'])
            ->keyBy('id');
    }
}
