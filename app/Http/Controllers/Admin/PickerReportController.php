<?php

namespace App\Http\Controllers\Admin;

use App\Exports\QcScanPerformanceExport;
use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\QcScanResi;
use App\Models\User;
use App\Support\QcScanPerformanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PickerReportController extends Controller
{
    public function index()
    {
        $authUser = request()->user();
        $divisiQuery = Divisi::orderBy('name');
        if ($authUser && $authUser->divisi_id !== null && (int) $authUser->divisi_id !== 1) {
            $divisiQuery->where('id', $authUser->divisi_id);
        }
        $divisis = $divisiQuery->get(['id', 'name']);

        return view('admin.outbound.picker-reports.index', [
            'dataUrl' => route('admin.outbound.picker-reports.data'),
            'exportUrl' => route('admin.outbound.picker-reports.export'),
            'divisis' => $divisis,
            'today' => now()->toDateString(),
            'generatedBy' => $authUser?->name ?? '-',
        ]);
    }

    public function data(Request $request)
    {
        $authUser = $request->user();
        $filters = $this->filters($request);
        $allowedDivisiId = $authUser?->divisi_id !== null ? (int) $authUser->divisi_id : null;
        $baseQuery = QcScanPerformanceReport::dailyQuery([], $allowedDivisiId, false);
        $query = QcScanPerformanceReport::dailyQuery($filters, $allowedDivisiId);

        $recordsTotal = DB::query()->fromSub($baseQuery, 't')->count();
        $recordsFiltered = DB::query()->fromSub($query, 't')->count();

        $summaryRow = DB::query()->fromSub($query, 't')
            ->selectRaw('COUNT(DISTINCT t.user_id) as petugas_count')
            ->selectRaw('COUNT(DISTINCT t.report_date) as day_count')
            ->selectRaw('COALESCE(SUM(t.total_resi), 0) as resi_total')
            ->selectRaw('COALESCE(SUM(t.completed_resi), 0) as completed_total')
            ->selectRaw('COALESCE(SUM(t.scanned_qty), 0) as qty_total')
            ->selectRaw('COALESCE(SUM(t.active_hours), 0) as active_hours_total')
            ->selectRaw('COALESCE(SUM(t.completion_seconds_total), 0) as completion_seconds_total')
            ->selectRaw('COALESCE(SUM(t.timed_completed_resi), 0) as timed_completed_total')
            ->first();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $rows = $query->get();

        $data = $rows->map(function ($row) {
            $firstScan = $row->first_scan_at ? Carbon::parse($row->first_scan_at)->format('H:i') : '';
            $lastScan = $row->last_scan_at ? Carbon::parse($row->last_scan_at)->format('H:i') : '';
            $range = ($firstScan !== '' && $lastScan !== '') ? "{$firstScan} - {$lastScan}" : '-';

            $totalResi = (int) $row->total_resi;
            $completed = (int) $row->completed_resi;
            $pending = (int) $row->pending_resi;
            $completionPct = $totalResi > 0 ? (int) round($completed / $totalResi * 100) : 0;
            $activeHours = (int) $row->active_hours;
            $timedCompleted = (int) $row->timed_completed_resi;

            return [
                'date' => $row->report_date,
                'user_id' => (int) $row->user_id,
                'petugas' => $row->petugas ?? '-',
                'total_resi' => $totalResi,
                'completed' => $completed,
                'pending' => $pending,
                'completion_pct' => $completionPct,
                'active_hours' => $activeHours,
                'resi_per_hour' => $activeHours > 0 ? round($totalResi / $activeHours, 1) : 0,
                'avg_completion_minutes' => $timedCompleted > 0
                    ? round(((int) $row->completion_seconds_total) / $timedCompleted / 60, 1)
                    : null,
                'sku_lines' => (int) $row->sku_lines,
                'required_qty' => (int) $row->required_qty,
                'scanned_qty' => (int) $row->scanned_qty,
                'range' => $range,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'summary' => [
                'petugas_count' => (int) ($summaryRow->petugas_count ?? 0),
                'day_count' => (int) ($summaryRow->day_count ?? 0),
                'resi_total' => (int) ($summaryRow->resi_total ?? 0),
                'completed_total' => (int) ($summaryRow->completed_total ?? 0),
                'qty_total' => (int) ($summaryRow->qty_total ?? 0),
                'active_hours_total' => (int) ($summaryRow->active_hours_total ?? 0),
                'resi_per_hour' => (int) ($summaryRow->active_hours_total ?? 0) > 0
                    ? round((int) ($summaryRow->resi_total ?? 0) / (int) $summaryRow->active_hours_total, 1)
                    : 0,
                'avg_completion_minutes' => (int) ($summaryRow->timed_completed_total ?? 0) > 0
                    ? round((int) $summaryRow->completion_seconds_total / (int) $summaryRow->timed_completed_total / 60, 1)
                    : null,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        $authUser = $request->user();
        $allowedDivisiId = $authUser?->divisi_id !== null ? (int) $authUser->divisi_id : null;
        $period = ($filters['date_from'] ?: 'awal').'-'.($filters['date_to'] ?: 'akhir');

        return Excel::download(
            new QcScanPerformanceExport($filters, $allowedDivisiId, $authUser?->name ?? '-'),
            'laporan-performa-qc-scan-'.$period.'.xlsx'
        );
    }

    public function detail(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $userId = (int) $validated['user_id'];

        $authUser = $request->user();
        if ($authUser && $authUser->divisi_id !== null && (int) $authUser->divisi_id !== 1) {
            $targetUser = User::find($userId);
            if (! $targetUser || (int) $targetUser->divisi_id !== (int) $authUser->divisi_id) {
                return response()->json(['message' => 'Tidak diizinkan'], 403);
            }
        }

        $qcResis = QcScanResi::query()
            ->with([
                'resi:id,no_resi,id_pesanan',
                'items:id,qc_scan_resi_id,item_id,sku,required_qty,scanned_qty',
                'items.item:id,name',
            ])
            ->where('scanned_by', $userId)
            ->whereDate('scanned_at', $date)
            ->orderBy('scanned_at')
            ->get();

        $resis = $qcResis->map(function ($qc) {
            $items = $qc->items->map(fn ($it) => [
                'sku' => $it->sku,
                'name' => $it->item?->name ?? '-',
                'required_qty' => (int) $it->required_qty,
                'scanned_qty' => (int) $it->scanned_qty,
            ])->values();

            return [
                'no_resi' => $qc->resi?->no_resi ?? '-',
                'id_pesanan' => $qc->resi?->id_pesanan ?? '-',
                'status' => $qc->status,
                'scanned_at' => $qc->scanned_at ? Carbon::parse($qc->scanned_at)->format('H:i') : '-',
                'completed_at' => $qc->completed_at ? Carbon::parse($qc->completed_at)->format('H:i') : '-',
                'completion_minutes' => $qc->completed_at && $qc->completed_at->gte($qc->scanned_at)
                    ? round($qc->scanned_at->diffInSeconds($qc->completed_at) / 60, 1)
                    : null,
                'sku_count' => $items->count(),
                'required_qty' => (int) $items->sum('required_qty'),
                'scanned_qty' => (int) $items->sum('scanned_qty'),
                'items' => $items,
            ];
        })->values();

        $totalResi = $resis->count();
        $completed = $qcResis->where('status', 'completed')->count();
        $hourly = $qcResis
            ->groupBy(fn ($qc) => Carbon::parse($qc->scanned_at)->format('H'))
            ->map(function ($rows, $hour) {
                $completedRows = $rows->where('status', 'completed');
                $timedRows = $completedRows->filter(fn ($qc) => $qc->completed_at && $qc->completed_at->gte($qc->scanned_at));
                $total = $rows->count();
                $completedCount = $completedRows->count();

                return [
                    'hour' => sprintf('%02d:00 - %02d:59', (int) $hour, (int) $hour),
                    'hour_no' => (int) $hour,
                    'total_resi' => $total,
                    'completed' => $completedCount,
                    'pending' => max(0, $total - $completedCount),
                    'completion_pct' => $total > 0 ? (int) round($completedCount / $total * 100) : 0,
                    'sku_lines' => (int) $rows->sum(fn ($qc) => $qc->items->count()),
                    'required_qty' => (int) $rows->sum(fn ($qc) => $qc->items->sum('required_qty')),
                    'scanned_qty' => (int) $rows->sum(fn ($qc) => $qc->items->sum('scanned_qty')),
                    'avg_completion_minutes' => $timedRows->isNotEmpty()
                        ? round($timedRows->avg(fn ($qc) => $qc->scanned_at->diffInSeconds($qc->completed_at) / 60), 1)
                        : null,
                ];
            })
            ->sortBy('hour_no')
            ->values();
        $peakHour = $hourly->sortByDesc('total_resi')->first();
        $timedQcResis = $qcResis->filter(fn ($qc) => $qc->status === 'completed' && $qc->completed_at && $qc->completed_at->gte($qc->scanned_at));

        return response()->json([
            'date' => $date,
            'petugas' => User::where('id', $userId)->value('name') ?? '-',
            'total_resi' => $totalResi,
            'completed' => $completed,
            'pending' => max(0, $totalResi - $completed),
            'total_sku' => (int) $resis->sum('sku_count'),
            'required_qty' => (int) $resis->sum('required_qty'),
            'scanned_qty' => (int) $resis->sum('scanned_qty'),
            'active_hours' => $hourly->count(),
            'resi_per_hour' => $hourly->count() > 0 ? round($totalResi / $hourly->count(), 1) : 0,
            'avg_completion_minutes' => $timedQcResis->isNotEmpty()
                ? round($timedQcResis->avg(fn ($qc) => $qc->scanned_at->diffInSeconds($qc->completed_at) / 60), 1)
                : null,
            'peak_hour' => $peakHour['hour'] ?? '-',
            'peak_hour_resi' => (int) ($peakHour['total_resi'] ?? 0),
            'hourly' => $hourly,
            'resis' => $resis,
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q', '')),
            'divisi_id' => $request->integer('divisi_id') ?: null,
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];
    }
}
