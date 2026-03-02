<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PickerSession;
use App\Models\PickerSessionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PickerReportController extends Controller
{
    public function index()
    {
        return view('admin.outbound.picker-reports.index', [
            'dataUrl' => route('admin.outbound.picker-reports.data'),
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
            $items = $this->fetchItems($row->report_date, (int) $row->user_id);
            $itemLabels = $items->map(function ($it) {
                $sku = trim((string) ($it->sku ?? ''));
                if ($sku === '') {
                    return '';
                }
                $qty = (int) ($it->qty ?? 0);
                return sprintf('%s (%d)', $sku, $qty);
            })->filter()->values();

            $first = $row->first_submitted_at
                ? Carbon::parse($row->first_submitted_at)->format('H:i')
                : '';
            $last = $row->last_submitted_at
                ? Carbon::parse($row->last_submitted_at)->format('H:i')
                : '';
            $range = ($first !== '' && $last !== '') ? "{$first} - {$last}" : '-';

            return [
                'date' => $row->report_date,
                'picker' => $row->picker ?? '-',
                'batch_count' => (int) $row->batch_count,
                'sku_count' => (int) $row->sku_count,
                'qty' => (int) $row->total_qty,
                'range' => $range,
                'items' => $itemLabels->implode(', ') ?: '-',
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
        $query = PickerSession::query()
            ->join('picker_session_items as psi', 'picker_sessions.id', '=', 'psi.picker_session_id')
            ->join('users', 'users.id', '=', 'picker_sessions.user_id')
            ->selectRaw('DATE(picker_sessions.submitted_at) as report_date')
            ->selectRaw('picker_sessions.user_id')
            ->selectRaw('users.name as picker')
            ->selectRaw('COUNT(DISTINCT picker_sessions.id) as batch_count')
            ->selectRaw('COUNT(DISTINCT psi.item_id) as sku_count')
            ->selectRaw('SUM(psi.qty) as total_qty')
            ->selectRaw('MIN(picker_sessions.submitted_at) as first_submitted_at')
            ->selectRaw('MAX(picker_sessions.submitted_at) as last_submitted_at')
            ->where('picker_sessions.status', 'submitted')
            ->whereNotNull('picker_sessions.submitted_at')
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
}
