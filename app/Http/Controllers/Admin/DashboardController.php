<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurir;
use App\Models\PackerScanOut;
use App\Models\Resi;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $totalResi = Resi::whereDate('tanggal_upload', $today)->count();
        $totalScanOut = PackerScanOut::whereDate('scan_date', $today)->count();

        $resiCounts = Resi::select('kurir_id', DB::raw('count(*) as total'))
            ->whereDate('tanggal_upload', $today)
            ->groupBy('kurir_id')
            ->pluck('total', 'kurir_id')
            ->toArray();

        $scanCounts = PackerScanOut::select('kurir_id', DB::raw('count(*) as total'))
            ->whereDate('scan_date', $today)
            ->groupBy('kurir_id')
            ->pluck('total', 'kurir_id')
            ->toArray();

        $kurirs = Kurir::orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($kurir) use ($resiCounts, $scanCounts) {
                $resiTotal = (int) ($resiCounts[$kurir->id] ?? 0);
                $scanTotal = (int) ($scanCounts[$kurir->id] ?? 0);
                return [
                    'id' => $kurir->id,
                    'name' => $kurir->name,
                    'resi_total' => $resiTotal,
                    'scan_total' => $scanTotal,
                    'remaining' => max(0, $resiTotal - $scanTotal),
                ];
            });

        return view('admin.dashboard', [
            'today' => $today,
            'totalResi' => $totalResi,
            'totalScanOut' => $totalScanOut,
            'kurirs' => $kurirs,
        ]);
    }
}
