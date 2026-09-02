<?php

namespace App\Exports;

use App\Models\StockMutation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class StockMutationsExport
{
    public function __construct(
        private string $search = '',
        private ?string $dateFrom = null,
        private ?string $dateTo = null
    ) {
    }

    public function query(): Builder
    {
        return $this->filteredQuery()
            ->with(['item.category', 'creator'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return [
            'No',
            'ID',
            'Tanggal',
            'SKU',
            'Nama Item',
            'Kategori',
            'UOM',
            'Lokasi',
            'Submit By',
            'Arah',
            'Qty Masuk',
            'Qty Keluar',
            'Mutasi Bersih',
            'Stok Sebelum',
            'Stok Sesudah',
            'Tipe Sumber',
            'Subtipe Sumber',
            'ID Sumber',
            'Kode Sumber',
            'Catatan',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 10,
            'C' => 18,
            'D' => 18,
            'E' => 32,
            'F' => 22,
            'G' => 10,
            'H' => 22,
            'I' => 24,
            'J' => 10,
            'K' => 12,
            'L' => 12,
            'M' => 14,
            'N' => 15,
            'O' => 15,
            'P' => 18,
            'Q' => 20,
            'R' => 12,
            'S' => 22,
            'T' => 40,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function rows(): \Generator
    {
        $number = 1;
        foreach ($this->query()->lazy($this->chunkSize()) as $mutation) {
            yield $this->map($mutation, $number);
            $number++;
        }
    }

    public function map($row, int $number = 1): array
    {
        $stockBefore = $row->stockBeforeValue();
        $stockAfter = $row->stock_after !== null
            ? (int) $row->stock_after
            : ($stockBefore === null
                ? null
                : ($row->direction === 'out'
                    ? $stockBefore - (int) $row->qty
                    : $stockBefore + (int) $row->qty));

        return [
            $number,
            $row->id,
            $row->occurred_at ? Carbon::parse($row->occurred_at)->format('Y-m-d H:i') : '',
            $row->item?->sku ?? '',
            $row->item?->name ?? '',
            $row->item?->category?->name ?? ((int) ($row->item?->category_id ?? 0) === 0 ? 'Tanpa Kategori' : '-'),
            $row->item?->uom ?? '-',
            $row->item?->address ?? '-',
            $row->creator?->name ?? '-',
            $row->direction === 'in' ? 'IN' : 'OUT',
            $row->direction === 'in' ? (int) $row->qty : 0,
            $row->direction === 'out' ? (int) $row->qty : 0,
            $row->direction === 'out' ? -((int) $row->qty) : (int) $row->qty,
            $stockBefore,
            $stockAfter,
            strtoupper((string) ($row->source_type ?? '-')),
            $row->source_subtype ?: '-',
            $row->source_id ? (int) $row->source_id : null,
            $row->source_code ?? '',
            $row->note ?? '',
        ];
    }

    public function workbookSheets(string $generatedBy = '-'): array
    {
        return [
            [
                'name' => 'Ringkasan',
                'headings' => ['Informasi Laporan', 'Nilai'],
                'rows' => $this->summaryRows($generatedBy),
                'column_widths' => ['A' => 30, 'B' => 42],
            ],
            [
                'name' => 'Rekap Sumber',
                'headings' => [
                    'Tipe Sumber',
                    'Subtipe Sumber',
                    'Jumlah Mutasi',
                    'Jumlah SKU',
                    'Qty Masuk',
                    'Qty Keluar',
                    'Mutasi Bersih',
                    'Mutasi Pertama',
                    'Mutasi Terakhir',
                ],
                'rows' => $this->sourceSummaryRows(),
                'column_widths' => [
                    'A' => 20,
                    'B' => 22,
                    'C' => 16,
                    'D' => 14,
                    'E' => 14,
                    'F' => 14,
                    'G' => 16,
                    'H' => 18,
                    'I' => 18,
                ],
            ],
            [
                'name' => 'Detail Mutasi',
                'headings' => $this->headings(),
                'rows' => $this->rows(),
                'column_widths' => $this->columnWidths(),
            ],
        ];
    }

    private function summaryRows(string $generatedBy): array
    {
        $summary = $this->filteredQuery()
            ->selectRaw('COUNT(*) as total_mutations')
            ->selectRaw('COUNT(DISTINCT item_id) as total_sku')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE 0 END), 0) as total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN qty ELSE 0 END), 0) as total_out")
            ->selectRaw('MIN(occurred_at) as first_at')
            ->selectRaw('MAX(occurred_at) as last_at')
            ->first();

        $totalIn = (int) ($summary?->total_in ?? 0);
        $totalOut = (int) ($summary?->total_out ?? 0);

        return [
            ['Nama Laporan', 'Laporan Mutasi Stok'],
            ['Periode Filter', ($this->dateFrom ?: 'Awal').' s/d '.($this->dateTo ?: 'Akhir')],
            ['Pencarian', trim($this->search) !== '' ? trim($this->search) : 'Semua data'],
            ['Dibuat Pada', now()->format('Y-m-d H:i:s')],
            ['Dibuat Oleh', $generatedBy],
            ['Jumlah Mutasi', (int) ($summary?->total_mutations ?? 0)],
            ['Jumlah SKU', (int) ($summary?->total_sku ?? 0)],
            ['Total Qty Masuk', $totalIn],
            ['Total Qty Keluar', $totalOut],
            ['Mutasi Bersih', $totalIn - $totalOut],
            ['Mutasi Pertama', $summary?->first_at ? Carbon::parse($summary->first_at)->format('Y-m-d H:i') : '-'],
            ['Mutasi Terakhir', $summary?->last_at ? Carbon::parse($summary->last_at)->format('Y-m-d H:i') : '-'],
        ];
    }

    private function sourceSummaryRows(): \Generator
    {
        $query = $this->filteredQuery()
            ->selectRaw("COALESCE(source_type, '-') as report_source_type")
            ->selectRaw("COALESCE(source_subtype, '-') as report_source_subtype")
            ->selectRaw('COUNT(*) as mutation_count')
            ->selectRaw('COUNT(DISTINCT item_id) as sku_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE 0 END), 0) as total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN qty ELSE 0 END), 0) as total_out")
            ->selectRaw('MIN(occurred_at) as first_at')
            ->selectRaw('MAX(occurred_at) as last_at')
            ->groupBy('source_type', 'source_subtype')
            ->orderByDesc('mutation_count')
            ->orderBy('source_type');

        foreach ($query->cursor() as $row) {
            $totalIn = (int) $row->total_in;
            $totalOut = (int) $row->total_out;

            yield [
                strtoupper((string) $row->report_source_type),
                $row->report_source_subtype,
                (int) $row->mutation_count,
                (int) $row->sku_count,
                $totalIn,
                $totalOut,
                $totalIn - $totalOut,
                $row->first_at ? Carbon::parse($row->first_at)->format('Y-m-d H:i') : '-',
                $row->last_at ? Carbon::parse($row->last_at)->format('Y-m-d H:i') : '-',
            ];
        }
    }

    private function filteredQuery(): Builder
    {
        $query = StockMutation::query();

        $this->applySearch($query);
        $this->applyDateFilter($query);

        return $query;
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
