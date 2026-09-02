<?php

namespace App\Exports;

use App\Models\StockMutation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class StockMutationsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStrictNullComparison
{
    public function __construct(
        private string $search = '',
        private ?string $dateFrom = null,
        private ?string $dateTo = null
    ) {
    }

    public function query(): Builder
    {
        $query = StockMutation::query()
            ->with(['item', 'creator'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $this->applySearch($query);
        $this->applyDateFilter($query);

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tanggal',
            'SKU',
            'Nama Item',
            'Submit By',
            'Arah',
            'Qty',
            'Stok Sebelum',
            'Stok Sesudah',
            'Sumber',
            'Kode Sumber',
            'Catatan',
        ];
    }

    public function map($row): array
    {
        $source = strtoupper($row->source_type ?? '').($row->source_subtype ? ' / '.$row->source_subtype : '');
        $stockBefore = $row->stockBeforeValue();
        $stockAfter = $row->stock_after !== null
            ? (int) $row->stock_after
            : ($stockBefore === null
                ? null
                : ($row->direction === 'out'
                    ? $stockBefore - (int) $row->qty
                    : $stockBefore + (int) $row->qty));

        return [
            $row->id,
            $row->occurred_at ? Carbon::parse($row->occurred_at)->format('Y-m-d H:i') : '',
            $row->item?->sku ?? '',
            $row->item?->name ?? '',
            $row->creator?->name ?? '-',
            $row->direction === 'in' ? 'IN' : 'OUT',
            (int) $row->qty,
            $stockBefore,
            $stockAfter,
            trim($source),
            $row->source_code ?? '',
            $row->note ?? '',
        ];
    }

    private function applySearch(Builder $query): void
    {
        $search = trim($this->search);
        if ($search === '') {
            return;
        }

        $query->where(function ($q) use ($search) {
            $q->where('source_code', 'like', "%{$search}%")
                ->orWhere('source_type', 'like', "%{$search}%")
                ->orWhere('source_subtype', 'like', "%{$search}%")
                ->orWhereHas('creator', function ($userQ) use ($search) {
                    $userQ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('item', function ($itemQ) use ($search) {
                    $itemQ->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
        });
    }

    private function applyDateFilter(Builder $query): void
    {
        try {
            if ($this->dateFrom) {
                $query->where('occurred_at', '>=', Carbon::parse($this->dateFrom)->startOfDay());
            }

            if ($this->dateTo) {
                $query->where('occurred_at', '<=', Carbon::parse($this->dateTo)->endOfDay());
            }
        } catch (\Throwable) {
            // Ignore invalid date filters to match the page behavior.
        }
    }
}
