<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\PickerSession;
use App\Models\PickerSessionItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
            'divisis' => $divisis,
        ]);
    }

    public function data(Request $request)
    {
        $authUser = $request->user();
        $baseQuery = $this->buildReportQuery($request, $authUser, false);
        $query = $this->buildReportQuery($request, $authUser, true);

        $recordsTotal = DB::query()->fromSub($baseQuery, 't')->count();
        $recordsFiltered = DB::query()->fromSub($query, 't')->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $rows = $query->get();

        $data = $rows->map(function ($row) {
            $first = $row->first_submitted_at
                ? Carbon::parse($row->first_submitted_at)->format('H:i')
                : '';
            $last = $row->last_submitted_at
                ? Carbon::parse($row->last_submitted_at)->format('H:i')
                : '';
            $range = ($first !== '' && $last !== '') ? "{$first} - {$last}" : '-';

            return [
                'date' => $row->report_date,
                'user_id' => (int) $row->user_id,
                'picker' => $row->picker ?? '-',
                'batch_count' => (int) $row->batch_count,
                'sku_count' => (int) $row->sku_count,
                'qty' => (int) $row->total_qty,
                'range' => $range,
                'avg_duration' => $this->formatDuration((int) round($row->avg_seconds ?? 0)),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
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
        if ($authUser) {
            $divisiId = $authUser->divisi_id;
            if ($divisiId !== null && (int) $divisiId !== 1) {
                $targetUser = User::find($userId);
                if (!$targetUser || (int) $targetUser->divisi_id !== (int) $divisiId) {
                    return response()->json(['message' => 'Tidak diizinkan'], 403);
                }
            }
        }

        $items = $this->fetchItems($date, $userId);
        $totalQty = (int) $items->sum('qty');
        $skuCount = (int) $items->count();

        $batchQuery = PickerSession::query()
            ->where('status', 'submitted')
            ->whereDate('submitted_at', $date)
            ->where('user_id', $userId);

        $batchCount = (int) $batchQuery->count();
        $first = $batchQuery->min('submitted_at');
        $last = $batchQuery->max('submitted_at');

        $pickerName = User::where('id', $userId)->value('name') ?? '-';

        return response()->json([
            'date' => $date,
            'picker' => $pickerName,
            'batch_count' => $batchCount,
            'sku_count' => $skuCount,
            'qty' => $totalQty,
            'first_submitted_at' => $first ? Carbon::parse($first)->format('H:i') : '-',
            'last_submitted_at' => $last ? Carbon::parse($last)->format('H:i') : '-',
            'items' => $items,
        ]);
    }

    private function buildReportQuery(Request $request, $authUser, bool $applyFilters)
    {
        $query = PickerSession::query()
            ->join('picker_session_items as psi', 'picker_sessions.id', '=', 'psi.picker_session_id')
            ->join('users', 'users.id', '=', 'picker_sessions.user_id')
            ->selectRaw('DATE(picker_sessions.submitted_at) as report_date')
            ->selectRaw('picker_sessions.user_id')
            ->selectRaw('users.name as picker')
            ->selectRaw('COUNT(DISTINCT picker_sessions.id) as batch_count')
            ->selectRaw('COUNT(DISTINCT psi.item_id) as sku_count')
            ->selectRaw('SUM(psi.qty) as total_qty')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, picker_sessions.started_at, picker_sessions.submitted_at)) as avg_seconds')
            ->selectRaw('MIN(picker_sessions.submitted_at) as first_submitted_at')
            ->selectRaw('MAX(picker_sessions.submitted_at) as last_submitted_at')
            ->where('picker_sessions.status', 'submitted')
            ->whereNotNull('picker_sessions.submitted_at')
            ->whereNotNull('picker_sessions.started_at')
            ->groupByRaw('DATE(picker_sessions.submitted_at)')
            ->groupBy('picker_sessions.user_id', 'users.name')
            ->orderByRaw('DATE(picker_sessions.submitted_at) desc')
            ->orderBy('users.name');

        if ($authUser) {
            $divisiId = $authUser->divisi_id;
            if ($divisiId !== null && (int) $divisiId !== 1) {
                $query->where('users.divisi_id', $divisiId);
            }
        }

        if ($applyFilters) {
            $search = trim((string) $request->input('q', ''));
            if ($search !== '') {
                $query->where('users.name', 'like', "%{$search}%");
            }
            $divisiId = $request->integer('divisi_id');
            if ($divisiId) {
                $query->where('users.divisi_id', $divisiId);
            }

            $this->applyDateFilter($query, $request);
        }

        return $query;
    }

    private function fetchItems(string $date, int $userId)
    {
        return PickerSessionItem::query()
            ->join('picker_sessions', 'picker_sessions.id', '=', 'picker_session_items.picker_session_id')
            ->join('items', 'items.id', '=', 'picker_session_items.item_id')
            ->where('picker_sessions.status', 'submitted')
            ->whereDate('picker_sessions.submitted_at', $date)
            ->where('picker_sessions.user_id', $userId)
            ->groupBy('items.id', 'items.sku', 'items.name')
            ->selectRaw('items.sku, items.name, SUM(picker_session_items.qty) as qty')
            ->orderBy('items.sku')
            ->get();
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('picker_sessions.submitted_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('picker_sessions.submitted_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '-';
        }
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        if ($hours > 0) {
            return sprintf('%dj %dm', $hours, $minutes);
        }
        return sprintf('%dm', $minutes);
    }
}
