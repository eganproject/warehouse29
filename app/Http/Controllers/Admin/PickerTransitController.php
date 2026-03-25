<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PickerTransitItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PickerTransitController extends Controller
{
    public function index()
    {
        return view('admin.inventory.picker-transit.index', [
            'dataUrl' => route('admin.inventory.picker-transit.data'),
        ]);
    }

    public function data(Request $request)
    {
        $query = PickerTransitItem::query()
            ->with('item')
            ->orderBy('picked_date', 'desc')
            ->orderBy('id', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->whereHas('item', function ($itemQ) use ($search) {
                $itemQ->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $this->applyDateFilter($query, $request);

        $recordsTotal = PickerTransitItem::count();
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
            'data' => $data,
        ]);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $date = $request->input('date');

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->where('picked_date', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }
}
