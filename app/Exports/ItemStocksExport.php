<?php

namespace App\Exports;

use App\Models\Item;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ItemStocksExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private string $search = '', private string $searchMode = 'like')
    {
    }

    public function collection(): Collection
    {
        $query = Item::active()->with('stock')->orderBy('name');
        $search = trim($this->search);
        $this->applySearch($query, $search, $this->searchMode === 'exact' ? 'exact' : 'like');

        return $query->get();
    }

    public function headings(): array
    {
        return ['ID', 'SKU', 'Nama', 'Stok'];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->sku,
            $row->name,
            (int) ($row->stock?->stock ?? 0),
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
