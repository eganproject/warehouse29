<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan resi per toko berdasarkan tanggal upload (import resi).
 *
 * Filter: date_from, date_to (Y-m-d), toko_id, channel_id (id | 'none'), q (nama toko).
 */
class TokoReport
{
    private const ACTIVE = "(r.status IS NULL OR r.status != 'canceled')";

    public static function filters(array $input): array
    {
        $today = now()->toDateString();

        try {
            $from = Carbon::parse($input['date_from'] ?? null ?: $today)->startOfDay();
            $to = Carbon::parse($input['date_to'] ?? null ?: $today)->startOfDay();
        } catch (\Throwable) {
            $from = Carbon::parse($today);
            $to = Carbon::parse($today);
        }
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'toko_id' => TokoFilter::normalize($input['toko_id'] ?? null),
            'channel_id' => TokoFilter::normalize($input['channel_id'] ?? null),
            'q' => trim((string) ($input['q'] ?? '')),
        ];
    }

    /**
     * Resi pada periode & filter, sudah join toko/channel dan status QC/scan out.
     */
    public static function resiQuery(array $filters): Builder
    {
        $qc = DB::table('qc_scan_resis')
            ->select('resi_id')
            ->selectRaw("MAX(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as qc_completed")
            ->groupBy('resi_id');
        $scan = DB::table('packer_scan_outs')
            ->select('resi_id')
            ->selectRaw('MIN(scanned_at) as scanned_at')
            ->groupBy('resi_id');
        $qty = DB::table('resi_details')
            ->select('resi_id')
            ->selectRaw('COALESCE(SUM(qty), 0) as total_qty')
            ->groupBy('resi_id');

        $query = DB::table('resis as r')
            ->leftJoin('tokos as t', 't.id', '=', 'r.toko_id')
            ->leftJoin('channels as c', 'c.id', '=', 'r.channel_id')
            ->leftJoinSub($qc, 'qs', 'qs.resi_id', '=', 'r.id')
            ->leftJoinSub($scan, 'pso', 'pso.resi_id', '=', 'r.id')
            ->leftJoinSub($qty, 'rd', 'rd.resi_id', '=', 'r.id')
            ->where('r.tanggal_upload', '>=', $filters['date_from'])
            ->where('r.tanggal_upload', '<', Carbon::parse($filters['date_to'])->addDay()->toDateString());

        TokoFilter::apply($query, $filters['toko_id'] ?? null, 'r.toko_id');
        TokoFilter::apply($query, $filters['channel_id'] ?? null, 'r.channel_id');

        $search = $filters['q'] ?? '';
        if ($search !== '') {
            $query->where('t.name', 'like', "%{$search}%");
        }

        return $query;
    }

    /**
     * Rekap per toko, urut dari resi aktif terbanyak.
     */
    public static function perToko(array $filters): Collection
    {
        $active = self::ACTIVE;

        $rows = self::resiQuery($filters)
            ->groupBy('r.toko_id', 't.name')
            ->select('r.toko_id', 't.name as toko_name')
            ->selectRaw("SUM(CASE WHEN {$active} THEN 1 ELSE 0 END) as active_total")
            ->selectRaw("SUM(CASE WHEN r.status = 'canceled' THEN 1 ELSE 0 END) as canceled_total")
            ->selectRaw("SUM(CASE WHEN {$active} AND qs.resi_id IS NOT NULL THEN 1 ELSE 0 END) as qc_total")
            ->selectRaw("SUM(CASE WHEN {$active} AND pso.resi_id IS NOT NULL THEN 1 ELSE 0 END) as scan_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$active} THEN rd.total_qty ELSE 0 END), 0) as total_qty")
            ->selectRaw('MIN(pso.scanned_at) as first_scan_at')
            ->selectRaw('MAX(pso.scanned_at) as last_scan_at')
            ->get();

        $channels = self::channelsPerToko($filters);
        $grandActive = (int) $rows->sum('active_total');

        return $rows->map(function ($row) use ($channels, $grandActive) {
            $active = (int) $row->active_total;
            $canceled = (int) $row->canceled_total;
            $scan = (int) $row->scan_total;
            $qty = (int) $row->total_qty;
            $key = $row->toko_id ? (int) $row->toko_id : 0;

            return [
                'toko_id' => $row->toko_id ? (int) $row->toko_id : null,
                'toko' => $row->toko_id ? ($row->toko_name ?? 'Toko #'.$row->toko_id) : 'Tanpa Toko',
                'channels' => $channels->get($key, '-'),
                'total_resi' => $active,
                'share' => $grandActive > 0 ? round($active / $grandActive * 100, 1) : 0,
                'qc_total' => (int) $row->qc_total,
                'scan_total' => $scan,
                'remaining' => max(0, $active - $scan),
                'completion_rate' => $active > 0 ? round($scan / $active * 100, 1) : 0,
                'canceled_total' => $canceled,
                'cancel_rate' => ($active + $canceled) > 0 ? round($canceled / ($active + $canceled) * 100, 1) : 0,
                'total_qty' => $qty,
                'avg_qty' => $active > 0 ? round($qty / $active, 1) : 0,
                'first_scan_at' => $row->first_scan_at ? Carbon::parse($row->first_scan_at)->format('Y-m-d H:i') : '-',
                'last_scan_at' => $row->last_scan_at ? Carbon::parse($row->last_scan_at)->format('Y-m-d H:i') : '-',
            ];
        })
            ->sortBy([['total_resi', 'desc'], ['toko', 'asc']])
            ->values();
    }

    public static function summary(Collection $perToko): array
    {
        $active = (int) $perToko->sum('total_resi');
        $canceled = (int) $perToko->sum('canceled_total');
        $scan = (int) $perToko->sum('scan_total');
        $top = $perToko->first(fn ($row) => $row['total_resi'] > 0);

        return [
            'toko_count' => $perToko->filter(fn ($row) => $row['toko_id'] !== null)->count(),
            'total_resi' => $active,
            'scan_total' => $scan,
            'remaining' => max(0, $active - $scan),
            'canceled_total' => $canceled,
            'completion_rate' => $active > 0 ? round($scan / $active * 100, 1) : 0,
            'cancel_rate' => ($active + $canceled) > 0 ? round($canceled / ($active + $canceled) * 100, 1) : 0,
            'total_qty' => (int) $perToko->sum('total_qty'),
            'top_toko' => $top['toko'] ?? '-',
            'top_toko_resi' => (int) ($top['total_resi'] ?? 0),
            'without_toko' => (int) ($perToko->firstWhere('toko_id', null)['total_resi'] ?? 0),
        ];
    }

    /**
     * Resi aktif & batal per hari per toko.
     */
    public static function daily(array $filters): Collection
    {
        $active = self::ACTIVE;

        return self::resiQuery($filters)
            ->groupByRaw('DATE(r.tanggal_upload)')
            ->groupBy('r.toko_id', 't.name')
            ->selectRaw('DATE(r.tanggal_upload) as report_date')
            ->selectRaw('r.toko_id, t.name as toko_name')
            ->selectRaw("SUM(CASE WHEN {$active} THEN 1 ELSE 0 END) as active_total")
            ->selectRaw("SUM(CASE WHEN r.status = 'canceled' THEN 1 ELSE 0 END) as canceled_total")
            ->selectRaw("SUM(CASE WHEN {$active} AND pso.resi_id IS NOT NULL THEN 1 ELSE 0 END) as scan_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$active} THEN rd.total_qty ELSE 0 END), 0) as total_qty")
            ->orderByRaw('DATE(r.tanggal_upload)')
            ->orderBy('t.name')
            ->get()
            ->map(function ($row) {
                $active = (int) $row->active_total;
                $scan = (int) $row->scan_total;

                return [
                    'date' => (string) $row->report_date,
                    'toko' => $row->toko_id ? ($row->toko_name ?? 'Toko #'.$row->toko_id) : 'Tanpa Toko',
                    'total_resi' => $active,
                    'scan_total' => $scan,
                    'remaining' => max(0, $active - $scan),
                    'canceled_total' => (int) $row->canceled_total,
                    'total_qty' => (int) $row->total_qty,
                ];
            });
    }

    /**
     * Query detail per resi untuk export (di-chunk oleh Excel).
     */
    public static function detailQuery(array $filters): Builder
    {
        return self::resiQuery($filters)
            ->leftJoin('kurirs as k', 'k.id', '=', 'r.kurir_id')
            ->select([
                'r.id', 'r.tanggal_upload', 'r.tanggal_pesanan', 'r.id_pesanan', 'r.no_resi', 'r.status',
                't.name as toko_name', 'c.name as channel_name', 'k.name as kurir_name',
                'qs.resi_id as qc_resi_id', 'qs.qc_completed', 'pso.scanned_at', 'rd.total_qty',
            ])
            ->orderBy('r.tanggal_upload')
            ->orderBy('t.name')
            ->orderBy('r.id');
    }

    /**
     * Daftar nama channel per toko_id (0 = tanpa toko), dipisah koma.
     */
    private static function channelsPerToko(array $filters): Collection
    {
        return self::resiQuery($filters)
            ->whereNotNull('r.channel_id')
            ->select('r.toko_id', 'c.name as channel_name')
            ->distinct()
            ->orderBy('c.name')
            ->get()
            ->groupBy(fn ($row) => $row->toko_id ? (int) $row->toko_id : 0)
            ->map(fn ($rows) => $rows->pluck('channel_name')->filter()->unique()->implode(', ') ?: '-');
    }
}
