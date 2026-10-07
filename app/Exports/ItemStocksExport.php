<?php

namespace App\Exports;

use App\Models\Item;
use App\Support\BundleService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ItemStocksExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    /** @var array<int,int> */
    private array $virtualStocks = [];

    public function __construct(private string $search = '', private string $searchMode = 'like')
    {
    }

    public function collection(): Collection
    {
        $query = Item::active()->with('stock')->orderBy('name');
        $search = trim($this->search);
        $this->applySearch($query, $search, $this->searchMode === 'exact' ? 'exact' : 'like');

        $items = $query->get();
        $this->virtualStocks = BundleService::getVirtualStockBatch($items->where('is_bundle', true)->pluck('id')->all());

        return $items;
    }

    public function headings(): array
    {
        return ['ID', 'SKU', 'Nama', 'Stok', 'Tipe'];
    }

    public function map($row): array
    {
        $isBundle = (bool) $row->is_bundle;

        return [
            $row->id,
            $row->sku,
            $row->name,
            $isBundle
                ? (int) ($this->virtualStocks[$row->id] ?? 0)
                : (int) ($row->stock?->stock ?? 0),
            $isBundle ? 'Bundle (stok virtual)' : 'Biasa',
        ];
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
}
