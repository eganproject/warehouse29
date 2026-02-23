<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StockMutationController extends Controller
{
    public function index()
    {
        return view('admin.inventory.stock-mutations.index');
    }

    public function data(Request $request)
    {
        $query = StockMutation::query()
            ->with('item')
            ->orderBy('occurred_at', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('source_code', 'like', "%{$search}%")
                    ->orWhere('source_type', 'like', "%{$search}%")
                    ->orWhere('source_subtype', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = StockMutation::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($m) {
            $itemLabel = trim(($m->item?->sku ?? '').' - '.($m->item?->name ?? ''));
            $ts = $m->occurred_at ? Carbon::parse($m->occurred_at)->format('Y-m-d H:i') : '';
            $direction = $m->direction === 'in' ? 'IN' : 'OUT';
            $source = strtoupper($m->source_type ?? '').($m->source_subtype ? ' / '.$m->source_subtype : '');
            return [
                'id' => $m->id,
                'occurred_at' => $ts,
                'item' => $itemLabel,
                'direction' => $direction,
                'qty' => (int) $m->qty,
                'source' => trim($source),
                'source_code' => $m->source_code ?? '',
                'note' => $m->note ?? '',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }
}
