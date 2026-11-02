<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Barang keluar per toko dipecah per kategori barang, misal "Kategori A keluar 500 dari 1.000 pcs toko X".
 */
class StockMovementTokoCategorySheet extends TokoReportTableSheet implements FromCollection
{
    public function __construct(private Collection $rows) {}

    public function title(): string
    {
        return 'Kategori per Toko';
    }

    public function headings(): array
    {
        return [
            'Toko',
            'Kategori',
            'Qty Keluar',
            'Total Qty Toko',
            'Porsi di Toko (%)',
            'Total Qty Kategori',
            'Porsi dari Total Kategori (%)',
            'SKU Keluar',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows->values()->map(fn ($row) => [
            $this->safeText($row->toko),
            $this->safeText($row->category),
            (int) $row->qty_out,
            (int) $row->toko_total,
            $row->toko_total > 0 ? round($row->qty_out / $row->toko_total * 100, 2) : 0,
            (int) $row->category_total,
            $row->category_total > 0 ? round($row->qty_out / $row->category_total * 100, 2) : 0,
            (int) $row->sku_count,
        ]);
    }

    protected function widths(): array
    {
        return ['A' => 32, 'B' => 26, 'C' => 13, 'D' => 15, 'E' => 15, 'F' => 17, 'G' => 22, 'H' => 12];
    }
}
