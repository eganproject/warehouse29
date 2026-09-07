<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ResiReport
{
    public function build(array $filters): array
    {
        $start = Carbon::parse($filters['report_start']);
        $end = Carbon::parse($filters['report_end']);
        $counts = DB::table('resis')
            ->where('tanggal_upload', '>=', $start->toDateString())
            ->where('tanggal_upload', '<', $end->copy()->addDay()->toDateString())
            ->selectRaw('DATE(tanggal_upload) as date')
            ->selectRaw("SUM(CASE WHEN status IS NULL OR status != 'canceled' THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled")
            ->groupByRaw('DATE(tanggal_upload)')
            ->orderByRaw('DATE(tanggal_upload)')
            ->get()->keyBy('date');

        // Every calendar day contributes to the average, including days with no resis.
        $daily = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $row = $counts->get($key);
            $daily->push((object) [
                'date' => $key,
                'active' => (int) ($row->active ?? 0),
                'canceled' => (int) ($row->canceled ?? 0),
            ]);
        }

        $totalActive = $daily->sum('active');
        $highest = (int) $daily->max('active');
        $peakDays = $highest > 0 ? $daily->where('active', $highest)->values() : collect();

        return [
            'summary' => (object) [
                'active' => $totalActive,
                'canceled' => $daily->sum('canceled'),
                'days' => $daily->count(),
                'average' => $daily->isNotEmpty() ? $totalActive / $daily->count() : 0,
                'highest' => $highest,
                'peak_date' => $peakDays->first()?->date,
                'peak_days' => $peakDays->count(),
            ],
            'daily' => $daily,
        ];
    }
}
