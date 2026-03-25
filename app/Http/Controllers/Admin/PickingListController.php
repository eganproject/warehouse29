<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PickingList;
use App\Models\PickingListException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PickingListController extends Controller
{
    public function index()
    {
        return view('admin.inventory.picking-list.index', [
            'dataUrl' => route('admin.inventory.picking-list.data'),
            'dataUrlExceptions' => route('admin.inventory.picking-list.exceptions'),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = PickingList::query()
            ->with('item')
            ->orderBy('list_date', 'desc')
            ->orderBy('sku');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($baseQuery, $request);

        $recordsTotal = PickingList::count();
        $summaryQuery = clone $baseQuery;
        $summary = [
            'ongoing' => (clone $summaryQuery)->where('remaining_qty', '>', 0)->count(),
            'done' => (clone $summaryQuery)->where('remaining_qty', '<=', 0)->count(),
        ];

        $query = clone $baseQuery;
        $this->applyStatusFilter($query, $request);
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $item = $row->item;
            return [
                'date' => $row->list_date?->format('Y-m-d') ?? '-',
                'sku' => $row->sku ?? '-',
                'name' => $item?->name ?? '-',
                'qty' => (int) $row->qty,
                'remaining_qty' => (int) $row->remaining_qty,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function dataExceptions(Request $request)
    {
        $query = PickingListException::query()
            ->with('item')
            ->orderBy('list_date', 'desc')
            ->orderBy('sku');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($query, $request);

        $recordsTotal = PickingListException::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $item = $row->item;
            return [
                'date' => $row->list_date?->format('Y-m-d') ?? '-',
                'sku' => $row->sku ?? '-',
                'name' => $item?->name ?? '-',
                'qty' => (int) $row->qty,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $date = $request->input('date') ?: now()->toDateString();

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->where('list_date', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function applyStatusFilter($query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if ($status === 'ongoing') {
            $query->where('remaining_qty', '>', 0);
        } elseif ($status === 'done') {
            $query->where('remaining_qty', '<=', 0);
        }
    }
}
