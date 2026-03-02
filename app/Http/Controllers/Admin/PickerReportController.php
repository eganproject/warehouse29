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
            $firstStarted = $row->first_started_at
                ? Carbon::parse($row->first_started_at)->format('H:i')
                : '';
            $lastSubmitted = $row->last_submitted_at
                ? Carbon::parse($row->last_submitted_at)->format('H:i')
                : '';
            $range = ($firstStarted !== '' && $lastSubmitted !== '') ? "{$firstStarted} - {$lastSubmitted}" : '-';

            $batchCount = (int) $row->batch_count;
            $skuCount = (int) $row->sku_count;
            $totalQty = (int) $row->total_qty;
            $avgQty = $batchCount > 0 ? round($totalQty / $batchCount, 1) : 0;
            $avgSku = $batchCount > 0 ? round($skuCount / $batchCount, 1) : 0;
            $totalSeconds = (int) round($row->total_seconds ?? 0);
            $qtyPerHour = $totalSeconds > 0 ? round($totalQty / ($totalSeconds / 3600), 1) : 0;

            return [
                'date' => $row->report_date,
                'user_id' => (int) $row->user_id,
                'picker' => $row->picker ?? '-',
                'batch_count' => $batchCount,
                'sku_count' => $skuCount,
                'qty' => $totalQty,
                'avg_qty' => $avgQty,
                'avg_sku' => $avgSku,
                'avg_duration' => $this->formatDuration((int) round($row->avg_seconds ?? 0)),
                'total_duration' => $this->formatDuration($totalSeconds),
                'productivity' => $qtyPerHour > 0 ? "{$qtyPerHour} qty/jam" : '-',
                'range' => $range,
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

        $batchStats = (clone $batchQuery)
            ->selectRaw('COUNT(*) as batch_count')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, submitted_at)) as avg_seconds')
            ->selectRaw('SUM(TIMESTAMPDIFF(SECOND, started_at, submitted_at)) as total_seconds')
            ->selectRaw('MIN(started_at) as first_started_at')
            ->selectRaw('MIN(submitted_at) as first_submitted_at')
            ->selectRaw('MAX(submitted_at) as last_submitted_at')
            ->whereNotNull('started_at')
            ->first();

        $batchCount = (int) ($batchStats->batch_count ?? 0);
        $first = $batchStats?->first_submitted_at ?? null;
        $last = $batchStats?->last_submitted_at ?? null;
        $firstStarted = $batchStats?->first_started_at ?? null;
        $avgSeconds = (int) round($batchStats?->avg_seconds ?? 0);
        $totalSeconds = (int) round($batchStats?->total_seconds ?? 0);
        $avgQty = $batchCount > 0 ? round($totalQty / $batchCount, 1) : 0;
        $avgSku = $batchCount > 0 ? round($skuCount / $batchCount, 1) : 0;
        $productivity = $totalSeconds > 0 ? round($totalQty / ($totalSeconds / 3600), 1) : 0;

        $pickerName = User::where('id', $userId)->value('name') ?? '-';

        return response()->json([
            'date' => $date,
            'picker' => $pickerName,
            'batch_count' => $batchCount,
            'sku_count' => $skuCount,
            'qty' => $totalQty,
            'avg_qty' => $avgQty,
            'avg_sku' => $avgSku,
            'avg_duration' => $this->formatDuration($avgSeconds),
            'total_duration' => $this->formatDuration($totalSeconds),
            'productivity' => $productivity > 0 ? "{$productivity} qty/jam" : '-',
            'first_started_at' => $firstStarted ? Carbon::parse($firstStarted)->format('H:i') : '-',
            'first_submitted_at' => $first ? Carbon::parse($first)->format('H:i') : '-',
            'last_submitted_at' => $last ? Carbon::parse($last)->format('H:i') : '-',
            'items' => $items,
        ]);
    }

    public function skuSummary(Request $request)
    {
        $authUser = $request->user();
        $baseQuery = $this->buildSkuSummaryQuery($request, $authUser, false);
        $query = $this->buildSkuSummaryQuery($request, $authUser, true);

        $recordsTotal = DB::query()->fromSub($baseQuery, 't')->count();
        $recordsFiltered = DB::query()->fromSub($query, 't')->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $rows = $query->get();

        $data = $rows->map(function ($row) {
            $totalQty = (int) $row->total_qty;
            $batchCount = (int) $row->batch_count;
            $avgQty = $batchCount > 0 ? round($totalQty / $batchCount, 1) : 0;

            return [
                'sku' => $row->sku ?? '-',
                'name' => $row->name ?? '-',
                'total_qty' => $totalQty,
                'batch_count' => $batchCount,
                'picker_count' => (int) $row->picker_count,
                'avg_qty' => $avgQty,
                'picker_list' => $row->picker_list ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function buildReportQuery(Request $request, $authUser, bool $applyFilters)
    {
        $sessionAgg = PickerSession::query()
            ->selectRaw('DATE(picker_sessions.submitted_at) as report_date')
            ->selectRaw('picker_sessions.user_id')
            ->selectRaw('COUNT(*) as batch_count')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, picker_sessions.started_at, picker_sessions.submitted_at)) as avg_seconds')
            ->selectRaw('SUM(TIMESTAMPDIFF(SECOND, picker_sessions.started_at, picker_sessions.submitted_at)) as total_seconds')
            ->selectRaw('MIN(picker_sessions.started_at) as first_started_at')
            ->selectRaw('MIN(picker_sessions.submitted_at) as first_submitted_at')
            ->selectRaw('MAX(picker_sessions.submitted_at) as last_submitted_at')
            ->where('picker_sessions.status', 'submitted')
            ->whereNotNull('picker_sessions.submitted_at')
            ->whereNotNull('picker_sessions.started_at')
            ->groupByRaw('DATE(picker_sessions.submitted_at)')
            ->groupBy('picker_sessions.user_id');

        $itemsAgg = PickerSessionItem::query()
            ->join('picker_sessions', 'picker_sessions.id', '=', 'picker_session_items.picker_session_id')
            ->selectRaw('DATE(picker_sessions.submitted_at) as report_date')
            ->selectRaw('picker_sessions.user_id as user_id')
            ->selectRaw('COUNT(DISTINCT picker_session_items.item_id) as sku_count')
            ->selectRaw('SUM(picker_session_items.qty) as total_qty')
            ->where('picker_sessions.status', 'submitted')
            ->whereNotNull('picker_sessions.submitted_at')
            ->groupByRaw('DATE(picker_sessions.submitted_at)')
            ->groupBy('picker_sessions.user_id');

        $query = DB::query()
            ->fromSub($sessionAgg, 's')
            ->join('users', 'users.id', '=', 's.user_id')
            ->leftJoinSub($itemsAgg, 'i', function ($join) {
                $join->on('i.report_date', '=', 's.report_date')
                    ->on('i.user_id', '=', 's.user_id');
            })
            ->selectRaw('s.report_date, s.user_id, users.name as picker, s.batch_count')
            ->selectRaw('COALESCE(i.sku_count, 0) as sku_count')
            ->selectRaw('COALESCE(i.total_qty, 0) as total_qty')
            ->selectRaw('s.avg_seconds, s.total_seconds, s.first_started_at, s.first_submitted_at, s.last_submitted_at')
            ->orderByRaw('s.report_date desc')
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

            $this->applyDateFilter($query, $request, 's.report_date', true);
        }

        return $query;
    }

    private function buildSkuSummaryQuery(Request $request, $authUser, bool $applyFilters)
    {
        $userAgg = PickerSessionItem::query()
            ->join('picker_sessions', 'picker_sessions.id', '=', 'picker_session_items.picker_session_id')
            ->join('items', 'items.id', '=', 'picker_session_items.item_id')
            ->join('users', 'users.id', '=', 'picker_sessions.user_id')
            ->selectRaw('picker_session_items.item_id as item_id')
            ->selectRaw('users.id as user_id')
            ->selectRaw('users.name as picker')
            ->selectRaw('SUM(picker_session_items.qty) as qty')
            ->where('picker_sessions.status', 'submitted')
            ->whereNotNull('picker_sessions.submitted_at')
            ->groupBy('picker_session_items.item_id', 'users.id', 'users.name');

        if ($authUser) {
            $divisiId = $authUser->divisi_id;
            if ($divisiId !== null && (int) $divisiId !== 1) {
                $userAgg->where('users.divisi_id', $divisiId);
            }
        }

        if ($applyFilters) {
            $search = trim((string) $request->input('q', ''));
            if ($search !== '') {
                $userAgg->where(function ($q) use ($search) {
                    $q->where('items.sku', 'like', "%{$search}%")
                        ->orWhere('items.name', 'like', "%{$search}%");
                });
            }
            $divisiId = $request->integer('divisi_id');
            if ($divisiId) {
                $userAgg->where('users.divisi_id', $divisiId);
            }

            $this->applyDateFilter($userAgg, $request);
        }

        $pickerListAgg = DB::query()
            ->fromSub($userAgg, 'u')
            ->selectRaw('u.item_id')
            ->selectRaw('GROUP_CONCAT(CONCAT(u.picker, " (", u.qty, ")") ORDER BY u.picker SEPARATOR ", ") as picker_list')
            ->groupBy('u.item_id');

        $query = PickerSessionItem::query()
            ->join('picker_sessions', 'picker_sessions.id', '=', 'picker_session_items.picker_session_id')
            ->join('items', 'items.id', '=', 'picker_session_items.item_id')
            ->join('users', 'users.id', '=', 'picker_sessions.user_id')
            ->leftJoinSub($pickerListAgg, 'p', function ($join) {
                $join->on('p.item_id', '=', 'items.id');
            })
            ->selectRaw('items.sku, items.name')
            ->selectRaw('SUM(picker_session_items.qty) as total_qty')
            ->selectRaw('COUNT(DISTINCT picker_sessions.id) as batch_count')
            ->selectRaw('COUNT(DISTINCT picker_sessions.user_id) as picker_count')
            ->selectRaw('COALESCE(MAX(p.picker_list), "-") as picker_list')
            ->where('picker_sessions.status', 'submitted')
            ->whereNotNull('picker_sessions.submitted_at')
            ->groupBy('items.id', 'items.sku', 'items.name')
            ->orderBy('items.sku');

        if ($authUser) {
            $divisiId = $authUser->divisi_id;
            if ($divisiId !== null && (int) $divisiId !== 1) {
                $query->where('users.divisi_id', $divisiId);
            }
        }

        if ($applyFilters) {
            $search = trim((string) $request->input('q', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('items.sku', 'like', "%{$search}%")
                        ->orWhere('items.name', 'like', "%{$search}%");
                });
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

    private function applyDateFilter($query, Request $request, string $column = 'picker_sessions.submitted_at', bool $dateOnly = false): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom);
                $query->where($column, '>=', $dateOnly ? $from->toDateString() : $from->startOfDay());
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo);
                $query->where($column, '<=', $dateOnly ? $to->toDateString() : $to->endOfDay());
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
