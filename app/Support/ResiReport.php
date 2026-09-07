<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ResiReport
{
    public const STAGES = [
        'pending' => 'Belum QC',
        'qc_progress' => 'QC berlangsung',
        'ready' => 'QC selesai, belum scan out',
        'scanned' => 'Sudah scan out',
        'canceled' => 'Dibatalkan',
    ];

    public function build(array $filters): array
    {
        // Aggregate item rows before joining so each resi is counted exactly once.
        $items = DB::table('resi_details')->select('resi_id')
            ->selectRaw('SUM(qty) as total_qty')->groupBy('resi_id');
        $base = DB::table('resis as r')
            ->leftJoin('kurirs as k', 'k.id', '=', 'r.kurir_id')
            ->leftJoin('qc_scan_resis as qc', 'qc.resi_id', '=', 'r.id')
            ->leftJoin('packer_scan_outs as so', 'so.resi_id', '=', 'r.id')
            ->leftJoinSub($items, 'items', 'items.resi_id', '=', 'r.id')
            ->where('r.tanggal_upload', '>=', $filters['report_start'])
            ->where('r.tanggal_upload', '<', Carbon::parse($filters['report_end'])->addDay()->toDateString())
            ->when($filters['report_kurir'] ?? null, fn ($q, $id) => $q->where('r.kurir_id', $id))
            ->select('r.id', 'r.id_pesanan', 'r.no_resi', 'r.kurir_id', 'r.cancel_reason', 'qc.completed_at', 'so.scanned_at')
            ->selectRaw('DATE(r.tanggal_upload) as tanggal_upload')
            ->selectRaw("COALESCE(k.name, 'Tanpa kurir') as courier_name, COALESCE(items.total_qty, 0) as total_qty")
            ->selectRaw("CASE WHEN r.status = 'canceled' THEN 'canceled'
                WHEN so.id IS NOT NULL THEN 'scanned'
                WHEN qc.status = 'completed' THEN 'ready'
                WHEN qc.id IS NOT NULL THEN 'qc_progress'
                ELSE 'pending' END as stage");

        $rows = DB::query()->fromSub($base, 'report');
        $aggregate = function ($query) {
            $query->selectRaw('COUNT(*) as total, COALESCE(SUM(total_qty), 0) as total_qty')
                ->selectRaw("COALESCE(SUM(CASE WHEN stage != 'canceled' THEN total_qty ELSE 0 END), 0) as active_qty");
            foreach (array_keys(self::STAGES) as $stage) {
                $query->selectRaw("COALESCE(SUM(CASE WHEN stage = ? THEN 1 ELSE 0 END), 0) as {$stage}", [$stage]);
            }

            return $query;
        };
        $summary = $aggregate(clone $rows)->first();
        $daily = $aggregate((clone $rows)->select('tanggal_upload'))->groupBy('tanggal_upload')
            ->orderBy('tanggal_upload')->get()->keyBy('tanggal_upload');
        // Include quiet days so the daily trend does not hide zero-volume dates.
        $dailyRows = collect();
        for ($date = Carbon::parse($filters['report_start']); $date->lte(Carbon::parse($filters['report_end'])); $date->addDay()) {
            $key = $date->toDateString();
            $dailyRows->push($daily->get($key) ?? (object) array_merge([
                'tanggal_upload' => $key, 'total' => 0, 'total_qty' => 0, 'active_qty' => 0,
            ], array_fill_keys(array_keys(self::STAGES), 0)));
        }
        $couriers = $aggregate((clone $rows)->select('kurir_id', 'courier_name'))
            ->groupBy('kurir_id', 'courier_name')->orderByRaw('(pending + qc_progress + ready) DESC')
            ->orderBy('courier_name')->get();
        $details = (clone $rows)
            ->when($filters['report_status'] ?? null, fn ($q, $stage) => $q->where('stage', $stage))
            ->orderByRaw("CASE stage WHEN 'pending' THEN 0 WHEN 'qc_progress' THEN 1 WHEN 'ready' THEN 2 WHEN 'scanned' THEN 3 ELSE 4 END")
            ->orderBy('tanggal_upload')->orderBy('id')->paginate(25, ['*'], 'report_page')
            ->withQueryString()->appends(['tab' => 'report'])->fragment('pane-report');

        return [
            'summary' => $summary,
            'daily' => $dailyRows,
            'couriers' => $couriers,
            'details' => $details,
            'stages' => self::STAGES,
        ];
    }
}
