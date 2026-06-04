<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OperationsDashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();
        $selectedDate = $request->query('date', $today);

        try {
            $selectedDate = Carbon::parse($selectedDate)->toDateString();
        } catch (\Throwable) {
            $selectedDate = $today;
        }

        $activeResi = DB::table('resis')
            ->whereDate('tanggal_upload', $selectedDate)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'canceled');
            });

        $totalResi = (clone $activeResi)->count();
        $canceledResi = DB::table('resis')
            ->whereDate('tanggal_upload', $selectedDate)
            ->where('status', 'canceled')
            ->count();
        $qcScanned = DB::table('qc_scan_resis as qs')
            ->join('resis as r', 'r.id', '=', 'qs.resi_id')
            ->whereDate('r.tanggal_upload', $selectedDate)
            ->distinct('qs.resi_id')
            ->count('qs.resi_id');
        $qcCompleted = DB::table('qc_scan_resis as qs')
            ->join('resis as r', 'r.id', '=', 'qs.resi_id')
            ->whereDate('r.tanggal_upload', $selectedDate)
            ->where('qs.status', 'completed')
            ->distinct('qs.resi_id')
            ->count('qs.resi_id');
        $scanOut = DB::table('packer_scan_outs as pso')
            ->join('resis as r', 'r.id', '=', 'pso.resi_id')
            ->whereDate('r.tanggal_upload', $selectedDate)
            ->distinct('pso.resi_id')
            ->count('pso.resi_id');

        $inboundReceipt = DB::table('inbound_transactions')
            ->where('type', 'receipt')
            ->whereDate('transacted_at', $selectedDate)
            ->count();
        $inboundReturn = DB::table('inbound_transactions')
            ->where('type', 'return')
            ->whereDate('transacted_at', $selectedDate)
            ->count();
        $outboundManual = DB::table('outbound_transactions')
            ->where('type', 'manual')
            ->whereDate('transacted_at', $selectedDate)
            ->count();
        $outboundReturn = DB::table('outbound_transactions')
            ->where('type', 'return')
            ->whereDate('transacted_at', $selectedDate)
            ->count();

        $courierAgg = DB::table('kurirs as k')
            ->leftJoin('resis as r', function ($join) use ($selectedDate) {
                $join->on('r.kurir_id', '=', 'k.id')
                    ->whereDate('r.tanggal_upload', $selectedDate);
            })
            ->leftJoin('packer_scan_outs as pso', 'pso.resi_id', '=', 'r.id')
            ->select('k.id', 'k.name')
            ->selectRaw("COUNT(DISTINCT CASE WHEN r.status IS NULL OR r.status != 'canceled' THEN r.id END) as total_resi")
            ->selectRaw("COUNT(DISTINCT CASE WHEN r.status = 'canceled' THEN r.id END) as canceled_total")
            ->selectRaw('COUNT(DISTINCT pso.resi_id) as scan_total')
            ->selectRaw('MAX(pso.scanned_at) as last_scan_at')
            ->groupBy('k.id', 'k.name');

        $couriers = DB::query()
            ->fromSub($courierAgg, 'courier_stats')
            ->whereRaw('total_resi + canceled_total + scan_total > 0')
            ->orderByRaw('(total_resi - scan_total) desc')
            ->orderByDesc('total_resi')
            ->limit(12)
            ->get()
            ->map(function ($row) {
                $total = (int) $row->total_resi;
                $scan = (int) $row->scan_total;

                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'total_resi' => $total,
                    'scan_total' => $scan,
                    'remaining' => max(0, $total - $scan),
                    'canceled_total' => (int) $row->canceled_total,
                    'completion_rate' => $total > 0 ? round(($scan / $total) * 100, 1) : 0,
                    'last_scan_at' => $row->last_scan_at ? Carbon::parse($row->last_scan_at)->format('H:i') : '-',
                ];
            });

        $lowStock = DB::table('items as i')
            ->leftJoin('item_stocks as s', 's.item_id', '=', 'i.id')
            ->where('i.safety_stock', '>', 0)
            ->whereRaw('COALESCE(s.stock, 0) < i.safety_stock')
            ->count();

        $outOfStock = DB::table('items as i')
            ->leftJoin('item_stocks as s', 's.item_id', '=', 'i.id')
            ->whereRaw('COALESCE(s.stock, 0) <= 0')
            ->count();

        return view('admin.dashboards.operations', [
            'today' => $selectedDate,
            'summary' => [
                'total_resi' => $totalResi,
                'qc_scanned' => $qcScanned,
                'qc_completed' => $qcCompleted,
                'scan_out' => $scanOut,
                'remaining_scan_out' => max(0, $totalResi - $scanOut),
                'canceled_resi' => $canceledResi,
                'scan_out_rate' => $totalResi > 0 ? round(($scanOut / $totalResi) * 100, 1) : 0,
                'qc_rate' => $totalResi > 0 ? round(($qcScanned / $totalResi) * 100, 1) : 0,
                'inbound_receipt' => $inboundReceipt,
                'inbound_return' => $inboundReturn,
                'outbound_manual' => $outboundManual,
                'outbound_return' => $outboundReturn,
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
            ],
            'couriers' => $couriers,
        ]);
    }
}
