<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\ItemStocksExport;
use App\Models\Item;
use App\Support\BundleService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ItemStockController extends Controller
{
    public function index()
    {
        return view('admin.inventory.item-stocks.index');
    }

    public function data(Request $request)
    {
        $query = Item::with(['stock'])->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = Item::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $items = $query->get();

        $bundleItemIds = $items->where('is_bundle', true)->pluck('id')->all();
        $virtualStocks = BundleService::getVirtualStockBatch($bundleItemIds);

        $data = $items->map(function ($i) use ($virtualStocks) {
            $isBundle = (bool) $i->is_bundle;
            $stock = $isBundle
                ? ($virtualStocks[$i->id] ?? 0)
                : ($i->stock?->stock ?? 0);

            return [
                'id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'stock' => $stock,
                'is_bundle' => $isBundle,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $filename = 'item-stocks-'.now()->format('YmdHis').'.xlsx';

        return Excel::download(new ItemStocksExport($search), $filename);
    }
}
