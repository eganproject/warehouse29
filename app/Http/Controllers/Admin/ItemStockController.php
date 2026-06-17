<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\ItemStocksExport;
use App\Models\Item;
use App\Models\StockMutation;
use App\Support\BundleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ItemStockController extends Controller
{
    public function index()
    {
        return view('admin.inventory.item-stocks.index');
    }

    public function show(int $id, Request $request)
    {
        $item = Item::active()->with(['stock', 'category'])->findOrFail($id);

        $isBundle = (bool) $item->is_bundle;
        $currentStock = $isBundle
            ? BundleService::getVirtualStockBatch([$item->id])[$item->id] ?? 0
            : ($item->stock?->stock ?? 0);

        $mutationQuery = StockMutation::with('creator')
            ->where('item_id', $id);

        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));
        if ($dateFrom !== '') {
            try {
                $mutationQuery->where('occurred_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            } catch (\Throwable) {
                $dateFrom = '';
            }
        }
        if ($dateTo !== '') {
            try {
                $mutationQuery->where('occurred_at', '<=', Carbon::parse($dateTo)->endOfDay());
            } catch (\Throwable) {
                $dateTo = '';
            }
        }

        $source = trim((string) $request->input('source', ''));
        $sourceOptions = $this->mutationSourceOptions();
        if ($source !== '' && isset($sourceOptions[$source])) {
            [$sourceType, $sourceSubtype] = array_pad(explode(':', $source, 2), 2, '');
            $mutationQuery->where('source_type', $sourceType);
            if ($sourceSubtype !== '') {
                $mutationQuery->where('source_subtype', $sourceSubtype);
            }
        } else {
            $source = '';
        }

        $mutationQuery->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $perPage = (int) $request->input('per_page', 20);
        $mutations = $mutationQuery->paginate($perPage)->withQueryString();

        return view('admin.inventory.item-stocks.show', [
            'item' => $item,
            'currentStock' => $currentStock,
            'isBundle' => $isBundle,
            'mutations' => $mutations,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'source' => $source,
            'sourceOptions' => $sourceOptions,
        ]);
    }

    public function data(Request $request)
    {
        $query = Item::active()->with(['stock'])->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        $searchMode = $request->input('search_mode') === 'exact' ? 'exact' : 'like';
        $this->applySearch($query, $search, $searchMode);

        $recordsTotal = Item::active()->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $allowedLengths = [10, 20, 50, 100];
        if (! in_array($length, $allowedLengths, true)) {
            $length = 10;
        }
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
        $searchMode = $request->input('search_mode') === 'exact' ? 'exact' : 'like';
        $filename = 'item-stocks-'.now()->format('YmdHis').'.xlsx';

        return Excel::download(new ItemStocksExport($search, $searchMode), $filename);
    }

    private function applySearch($query, string $search, string $mode): void
    {
        if ($search === '') {
            return;
        }

        $skus = $this->parseSkuTerms($search);
        if ($mode === 'exact') {
            $query->whereIn('sku', $skus);

            return;
        }

        $query->where(function ($q) use ($search, $skus) {
            foreach ($skus as $sku) {
                $q->orWhere('sku', 'like', "%{$sku}%");
            }

            $q->orWhere('name', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    private function parseSkuTerms(string $search): array
    {
        $terms = preg_split('/[\s,;]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_map('trim', $terms ?: [])));
    }

    private function mutationSourceOptions(): array
    {
        return [
            'inbound' => 'Inbound - Semua',
            'inbound:receipt' => 'Penerimaan Barang',
            'inbound:return' => 'Retur Inbound',
            'inbound:return_good' => 'Retur Inbound - Stok Bagus',
            'inbound:opening' => 'Saldo Awal Import',
            'inbound_return' => 'Retur Inbound - Stok Rusak',
            'outbound' => 'Outbound - Semua',
            'outbound:manual' => 'Outbound Manual',
            'outbound:picker' => 'Outbound QC Scan',
            'outbound:return' => 'Retur Outbound',
            'adjustment' => 'Penyesuaian Stok',
            'opname' => 'Stock Opname',
            'damaged' => 'Barang Rusak',
            'damaged_allocation' => 'Alokasi Barang Rusak',
            'picking_exception' => 'Retur Exception Picking',
            'qc' => 'Picker Mobile',
            'qc_resi' => 'QC Scan Resi',
        ];
    }
}
