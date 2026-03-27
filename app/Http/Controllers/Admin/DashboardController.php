<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurir;
use App\Models\PackerScanOut;
use App\Models\Resi;
use Illuminate\Support\Carbon;
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

        $resiLatest = Resi::select('kurir_id', DB::raw('max(updated_at) as latest'))
            ->whereDate('tanggal_upload', $today)
            ->groupBy('kurir_id')
            ->pluck('latest', 'kurir_id')
            ->toArray();

        $scanLatest = PackerScanOut::select('kurir_id', DB::raw('max(scanned_at) as latest'))
            ->whereDate('scan_date', $today)
            ->groupBy('kurir_id')
            ->pluck('latest', 'kurir_id')
            ->toArray();

        $kurirs = Kurir::orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($kurir) use ($resiCounts, $scanCounts, $resiLatest, $scanLatest) {
                $resiTotal = (int) ($resiCounts[$kurir->id] ?? 0);
                $scanTotal = (int) ($scanCounts[$kurir->id] ?? 0);
                $latestResi = $resiLatest[$kurir->id] ?? null;
                $latestScan = $scanLatest[$kurir->id] ?? null;
                $latestRaw = $latestResi && $latestScan
                    ? (Carbon::parse($latestResi)->greaterThan(Carbon::parse($latestScan)) ? $latestResi : $latestScan)
                    : ($latestResi ?: $latestScan);
                $latestTime = $latestRaw ? Carbon::parse($latestRaw)->format('H:i') : '-';
                return [
                    'id' => $kurir->id,
                    'name' => $kurir->name,
                    'resi_total' => $resiTotal,
                    'scan_total' => $scanTotal,
                    'remaining' => max(0, $resiTotal - $scanTotal),
                    'last_update' => $latestTime,
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
