<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class TokoReportSummarySheet extends TokoReportTableSheet implements FromCollection
{
    public function __construct(private Collection $perToko) {}

    public function title(): string
    {
        return 'Ringkasan Toko';
    }

    public function headings(): array
    {
        return [
            'Toko', 'Channel', 'Resi Aktif', 'Porsi Resi (%)', 'Sudah QC', 'Sudah Scan Out', 'Sisa Belum Scan Out',
            'Penyelesaian (%)', 'Resi Batal', 'Rasio Batal (%)', 'Total Qty', 'Rata-rata Qty/Resi',
            'Scan Out Pertama', 'Scan Out Terakhir',
        ];
    }

    public function collection(): Collection
    {
        return $this->perToko->map(fn (array $row) => [
            $this->safeText($row['toko']),
            $this->safeText($row['channels']),
            $row['total_resi'],
            $row['share'],
            $row['qc_total'],
            $row['scan_total'],
            $row['remaining'],
            $row['completion_rate'],
            $row['canceled_total'],
            $row['cancel_rate'],
            $row['total_qty'],
            $row['avg_qty'],
            $row['first_scan_at'],
            $row['last_scan_at'],
        ]);
    }

    protected function widths(): array
    {
        return ['A' => 28, 'B' => 24, 'C' => 12, 'D' => 14, 'E' => 12, 'F' => 15, 'G' => 18, 'H' => 16,
            'I' => 12, 'J' => 14, 'K' => 12, 'L' => 18, 'M' => 18, 'N' => 18];
    }
}
