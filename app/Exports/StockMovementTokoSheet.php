<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Barang keluar per toko (berdasarkan resi sumber mutasi) untuk SKU sesuai filter.
 */
class StockMovementTokoSheet extends TokoReportTableSheet implements FromCollection
{
    public function __construct(private Collection $rows) {}

    public function title(): string
    {
        return 'Keluar per Toko';
    }

    public function headings(): array
    {
        return ['Peringkat', 'Toko', 'Qty Keluar', 'Kontribusi Qty (%)', 'Frekuensi Mutasi', 'SKU Keluar', 'Jumlah Resi', 'Hari Aktif'];
    }

    public function collection(): Collection
    {
        $total = (int) $this->rows->sum('qty_out');

        return $this->rows->values()->map(fn ($row, int $index) => [
            $index + 1,
            $this->safeText($row->toko),
            (int) $row->qty_out,
            $total > 0 ? round((int) $row->qty_out / $total * 100, 2) : 0,
            (int) $row->frequency,
            (int) $row->sku_count,
            (int) $row->resi_count,
            (int) $row->active_days,
        ]);
    }

    protected function widths(): array
    {
        return ['A' => 11, 'B' => 32, 'C' => 13, 'D' => 18, 'E' => 17, 'F' => 13, 'G' => 13, 'H' => 12];
    }
}
