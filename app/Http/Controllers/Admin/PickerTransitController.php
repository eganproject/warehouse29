<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\PackerTransitStatusExport;
use App\Exports\PickerTransitStatusExport;
use App\Models\PackerTransitHistory;
use App\Models\PickerTransitItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class PickerTransitController extends Controller
{
    public function index()
    {
        return view('admin.inventory.picker-transit.index', [
            'dataUrl' => route('admin.inventory.picker-transit.data'),
            'dataUrlPacker' => route('admin.inventory.picker-transit.packer-data'),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = PickerTransitItem::query()
            ->with('item')
            ->orderBy('picked_date', 'desc')
            ->orderBy('id', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->whereHas('item', function ($itemQ) use ($search) {
                $itemQ->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $this->applyDateFilter($baseQuery, $request);

        $recordsTotal = PickerTransitItem::count();
        $summaryQuery = clone $baseQuery;
        $summary = [
            'ongoing' => (clone $summaryQuery)->where('remaining_qty', '>', 0)->count(),
            'done' => (clone $summaryQuery)->where('remaining_qty', '<=', 0)->count(),
        ];

        $query = clone $baseQuery;
        $this->applyPickerStatusFilter($query, $request);
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $item = $row->item;
            return [
                'date' => $row->picked_date?->format('Y-m-d') ?? '-',
                'sku' => $item?->sku ?? '-',
                'name' => $item?->name ?? '-',
                'qty' => (int) $row->qty,
                'remaining_qty' => (int) $row->remaining_qty,
                'picked_at' => $row->picked_at?->format('Y-m-d H:i') ?? '-',
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

    public function dataPacker(Request $request)
    {
        $baseQuery = PackerTransitHistory::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('id_pesanan', 'like', "%{$search}%")
                    ->orWhere('no_resi', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $this->applyPackerDateFilter($baseQuery, $request);

        $recordsTotal = PackerTransitHistory::count();
        $summaryQuery = clone $baseQuery;
        $summary = [
            'pending' => (clone $summaryQuery)->where('status', 'menunggu scan out')->count(),
            'done' => (clone $summaryQuery)->where('status', 'selesai')->count(),
        ];

        $query = clone $baseQuery;
        $this->applyPackerStatusFilter($query, $request);
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            return [
                'created_at' => $row->created_at?->format('Y-m-d H:i') ?? '-',
                'id_pesanan' => $row->id_pesanan ?? '-',
                'no_resi' => $row->no_resi ?? '-',
                'status' => $row->status ?? '-',
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

    public function exportPickerStatus(Request $request)
    {
        $filters = [
            'q' => $request->input('q', ''),
            'date' => $request->input('date'),
            'status' => $request->input('status', ''),
        ];

        $date = $filters['date'] ?: now()->toDateString();
        $suffix = $filters['status'] ?: 'all';
        $filename = "picker-transit-{$date}-{$suffix}.xlsx";

        return Excel::download(new PickerTransitStatusExport($filters), $filename);
    }

    public function exportPackerStatus(Request $request)
    {
        $filters = [
            'q' => $request->input('q', ''),
            'date' => $request->input('date'),
            'status' => $request->input('status', ''),
        ];

        $date = $filters['date'] ?: now()->toDateString();
        $suffix = $filters['status'] ?: 'all';
        $filename = "packer-transit-{$date}-{$suffix}.xlsx";

        return Excel::download(new PackerTransitStatusExport($filters), $filename);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $date = $request->input('date') ?: now()->toDateString();

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->where('picked_date', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function applyPackerDateFilter($query, Request $request): void
    {
        $date = $request->input('date') ?: now()->toDateString();

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->whereDate('created_at', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function applyPickerStatusFilter($query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if ($status === 'ongoing') {
            $query->where('remaining_qty', '>', 0);
        } elseif ($status === 'done') {
            $query->where('remaining_qty', '<=', 0);
        }
    }

    private function applyPackerStatusFilter($query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if ($status === 'pending') {
            $query->where('status', 'menunggu scan out');
        } elseif ($status === 'done') {
            $query->where('status', 'selesai');
        }
    }
}
