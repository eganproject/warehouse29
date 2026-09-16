<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QcScanPerformanceReport
{
    public static function dailyQuery(array $filters = [], ?int $allowedDivisiId = null, bool $applyFilters = true): Builder
    {
        $resiAgg = DB::table('qc_scan_resis as qs')
            ->selectRaw('DATE(qs.scanned_at) as report_date')
            ->selectRaw('qs.scanned_by as user_id')
            ->selectRaw('COUNT(*) as total_resi')
            ->selectRaw("SUM(CASE WHEN qs.status = 'completed' THEN 1 ELSE 0 END) as completed_resi")
            ->selectRaw("SUM(CASE WHEN qs.status <> 'completed' THEN 1 ELSE 0 END) as pending_resi")
            ->selectRaw("COUNT(DISTINCT DATE_FORMAT(qs.scanned_at, '%Y-%m-%d %H')) as active_hours")
            ->selectRaw("SUM(CASE WHEN qs.status = 'completed' AND qs.completed_at >= qs.scanned_at THEN TIMESTAMPDIFF(SECOND, qs.scanned_at, qs.completed_at) ELSE 0 END) as completion_seconds_total")
            ->selectRaw("SUM(CASE WHEN qs.status = 'completed' AND qs.completed_at >= qs.scanned_at THEN 1 ELSE 0 END) as timed_completed_resi")
            ->selectRaw('MIN(qs.scanned_at) as first_scan_at')
            ->selectRaw('MAX(qs.scanned_at) as last_scan_at')
            ->whereNotNull('qs.scanned_at')
            ->whereNotNull('qs.scanned_by')
            ->groupByRaw('DATE(qs.scanned_at)')
            ->groupBy('qs.scanned_by');

        $itemAgg = DB::table('qc_scan_resi_items as qsi')
            ->join('qc_scan_resis as qs', 'qs.id', '=', 'qsi.qc_scan_resi_id')
            ->selectRaw('DATE(qs.scanned_at) as report_date')
            ->selectRaw('qs.scanned_by as user_id')
            ->selectRaw('COUNT(*) as sku_lines')
            ->selectRaw('COALESCE(SUM(qsi.required_qty), 0) as required_qty')
            ->selectRaw('COALESCE(SUM(qsi.scanned_qty), 0) as scanned_qty')
            ->whereNotNull('qs.scanned_at')
            ->whereNotNull('qs.scanned_by')
            ->groupByRaw('DATE(qs.scanned_at)')
            ->groupBy('qs.scanned_by');

        $query = DB::query()
            ->fromSub($resiAgg, 'r')
            ->join('users', 'users.id', '=', 'r.user_id')
            ->leftJoinSub($itemAgg, 'i', function ($join) {
                $join->on('i.report_date', '=', 'r.report_date')
                    ->on('i.user_id', '=', 'r.user_id');
            })
            ->selectRaw('r.report_date, r.user_id, users.name as petugas')
            ->selectRaw('r.total_resi, r.completed_resi, r.pending_resi, r.active_hours')
            ->selectRaw('r.completion_seconds_total, r.timed_completed_resi')
            ->selectRaw('r.first_scan_at, r.last_scan_at')
            ->selectRaw('COALESCE(i.sku_lines, 0) as sku_lines')
            ->selectRaw('COALESCE(i.required_qty, 0) as required_qty')
            ->selectRaw('COALESCE(i.scanned_qty, 0) as scanned_qty')
            ->orderByDesc('r.report_date')
            ->orderBy('users.name');

        self::applyUserScope($query, $allowedDivisiId);
        if ($applyFilters) {
            self::applyFilters($query, $filters, 'r.report_date');
        }

        return $query;
    }

    public static function hourlyQuery(array $filters = [], ?int $allowedDivisiId = null): Builder
    {
        $resiAgg = DB::table('qc_scan_resis as qs')
            ->selectRaw('DATE(qs.scanned_at) as report_date')
            ->selectRaw('qs.scanned_by as user_id')
            ->selectRaw('HOUR(qs.scanned_at) as hour_no')
            ->selectRaw('COUNT(*) as total_resi')
            ->selectRaw("SUM(CASE WHEN qs.status = 'completed' THEN 1 ELSE 0 END) as completed_resi")
            ->selectRaw("SUM(CASE WHEN qs.status <> 'completed' THEN 1 ELSE 0 END) as pending_resi")
            ->selectRaw("SUM(CASE WHEN qs.status = 'completed' AND qs.completed_at >= qs.scanned_at THEN TIMESTAMPDIFF(SECOND, qs.scanned_at, qs.completed_at) ELSE 0 END) as completion_seconds_total")
            ->selectRaw("SUM(CASE WHEN qs.status = 'completed' AND qs.completed_at >= qs.scanned_at THEN 1 ELSE 0 END) as timed_completed_resi")
            ->whereNotNull('qs.scanned_at')
            ->whereNotNull('qs.scanned_by')
            ->groupByRaw('DATE(qs.scanned_at), qs.scanned_by, HOUR(qs.scanned_at)');

        $itemAgg = DB::table('qc_scan_resi_items as qsi')
            ->join('qc_scan_resis as qs', 'qs.id', '=', 'qsi.qc_scan_resi_id')
            ->selectRaw('DATE(qs.scanned_at) as report_date')
            ->selectRaw('qs.scanned_by as user_id')
            ->selectRaw('HOUR(qs.scanned_at) as hour_no')
            ->selectRaw('COUNT(*) as sku_lines')
            ->selectRaw('COALESCE(SUM(qsi.required_qty), 0) as required_qty')
            ->selectRaw('COALESCE(SUM(qsi.scanned_qty), 0) as scanned_qty')
            ->whereNotNull('qs.scanned_at')
            ->whereNotNull('qs.scanned_by')
            ->groupByRaw('DATE(qs.scanned_at), qs.scanned_by, HOUR(qs.scanned_at)');

        $query = DB::query()
            ->fromSub($resiAgg, 'h')
            ->join('users', 'users.id', '=', 'h.user_id')
            ->leftJoinSub($itemAgg, 'i', function ($join) {
                $join->on('i.report_date', '=', 'h.report_date')
                    ->on('i.user_id', '=', 'h.user_id')
                    ->on('i.hour_no', '=', 'h.hour_no');
            })
            ->selectRaw('h.report_date, h.user_id, users.name as petugas, h.hour_no')
            ->selectRaw('h.total_resi, h.completed_resi, h.pending_resi')
            ->selectRaw('h.completion_seconds_total, h.timed_completed_resi')
            ->selectRaw('COALESCE(i.sku_lines, 0) as sku_lines')
            ->selectRaw('COALESCE(i.required_qty, 0) as required_qty')
            ->selectRaw('COALESCE(i.scanned_qty, 0) as scanned_qty')
            ->orderByDesc('h.report_date')
            ->orderBy('users.name')
            ->orderBy('h.hour_no');

        self::applyUserScope($query, $allowedDivisiId);
        self::applyFilters($query, $filters, 'h.report_date');

        return $query;
    }

    public static function detailQuery(array $filters = [], ?int $allowedDivisiId = null): Builder
    {
        $itemAgg = DB::table('qc_scan_resi_items')
            ->selectRaw('qc_scan_resi_id')
            ->selectRaw('COUNT(*) as sku_lines')
            ->selectRaw('COALESCE(SUM(required_qty), 0) as required_qty')
            ->selectRaw('COALESCE(SUM(scanned_qty), 0) as scanned_qty')
            ->groupBy('qc_scan_resi_id');

        $query = DB::table('qc_scan_resis as qs')
            ->join('users', 'users.id', '=', 'qs.scanned_by')
            ->leftJoin('resis', 'resis.id', '=', 'qs.resi_id')
            ->leftJoinSub($itemAgg, 'i', 'i.qc_scan_resi_id', '=', 'qs.id')
            ->selectRaw('DATE(qs.scanned_at) as report_date, users.name as petugas')
            ->selectRaw('resis.no_resi, resis.id_pesanan, qs.status, qs.scanned_at, qs.completed_at')
            ->selectRaw('COALESCE(i.sku_lines, 0) as sku_lines, COALESCE(i.required_qty, 0) as required_qty, COALESCE(i.scanned_qty, 0) as scanned_qty')
            ->selectRaw('CASE WHEN qs.completed_at >= qs.scanned_at THEN ROUND(TIMESTAMPDIFF(SECOND, qs.scanned_at, qs.completed_at) / 60, 2) ELSE NULL END as completion_minutes')
            ->whereNotNull('qs.scanned_at')
            ->whereNotNull('qs.scanned_by')
            ->orderByDesc('qs.scanned_at');

        self::applyUserScope($query, $allowedDivisiId);
        self::applyFilters($query, $filters, 'qs.scanned_at', true);

        return $query;
    }

    private static function applyUserScope(Builder $query, ?int $allowedDivisiId): void
    {
        if ($allowedDivisiId !== null && $allowedDivisiId !== 1) {
            $query->where('users.divisi_id', $allowedDivisiId);
        }
    }

    private static function applyFilters(Builder $query, array $filters, string $dateColumn, bool $isDateTime = false): void
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where('users.name', 'like', "%{$search}%");
        }

        $divisiId = (int) ($filters['divisi_id'] ?? 0);
        if ($divisiId > 0) {
            $query->where('users.divisi_id', $divisiId);
        }

        try {
            if (! empty($filters['date_from'])) {
                $date = Carbon::parse($filters['date_from']);
                $query->where($dateColumn, '>=', $isDateTime ? $date->startOfDay() : $date->toDateString());
            }
            if (! empty($filters['date_to'])) {
                $date = Carbon::parse($filters['date_to']);
                $query->where($dateColumn, '<=', $isDateTime ? $date->endOfDay() : $date->toDateString());
            }
        } catch (\Throwable) {
            // Ignore invalid date filters; the UI normally sends ISO dates.
        }
    }
}
