<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class TokoReportDailySheet extends TokoReportTableSheet implements FromCollection
{
    public function __construct(private Collection $daily) {}

    public function title(): string
    {
        return 'Tren Harian per Toko';
    }

    public function headings(): array
    {
        return ['Tanggal Upload', 'Toko', 'Resi Aktif', 'Sudah Scan Out', 'Sisa Belum Scan Out', 'Resi Batal', 'Total Qty'];
    }

    public function collection(): Collection
    {
        return $this->daily->map(fn (array $row) => [
            $row['date'],
            $this->safeText($row['toko']),
            $row['total_resi'],
            $row['scan_total'],
            $row['remaining'],
            $row['canceled_total'],
            $row['total_qty'],
        ]);
    }

    protected function widths(): array
    {
        return ['A' => 15, 'B' => 28, 'C' => 12, 'D' => 15, 'E' => 18, 'F' => 12, 'G' => 12];
    }
}
