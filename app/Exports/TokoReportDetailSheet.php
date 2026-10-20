<?php

namespace App\Exports;

use App\Support\TokoReport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;

class TokoReportDetailSheet extends TokoReportTableSheet implements FromQuery, WithMapping
{
    public function __construct(private array $filters) {}

    public function title(): string
    {
        return 'Detail Resi';
    }

    public function query(): Builder
    {
        return TokoReport::detailQuery($this->filters);
    }

    public function headings(): array
    {
        return [
            'Tanggal Upload', 'Tanggal Pesanan', 'Toko', 'Channel', 'Kurir', 'ID Pesanan', 'No Resi',
            'Status Resi', 'Status Proses', 'Waktu Scan Out', 'Total Qty',
        ];
    }

    public function map($row): array
    {
        $canceled = $row->status === 'canceled';
        if ($canceled) {
            $process = '-';
        } elseif ($row->scanned_at) {
            $process = 'Sudah Scan Out';
        } elseif ($row->qc_resi_id) {
            $process = (int) $row->qc_completed === 1 ? 'QC Selesai' : 'QC Berjalan';
        } else {
            $process = 'Belum QC';
        }

        return [
            $this->date($row->tanggal_upload),
            $this->date($row->tanggal_pesanan),
            $this->safeText($row->toko_name, 'Tanpa Toko'),
            $this->safeText($row->channel_name),
            $this->safeText($row->kurir_name),
            $this->safeText($row->id_pesanan),
            $this->safeText($row->no_resi),
            $canceled ? 'Dibatalkan' : 'Aktif',
            $process,
            $row->scanned_at ? Carbon::parse($row->scanned_at)->format('Y-m-d H:i') : '-',
            (int) ($row->total_qty ?? 0),
        ];
    }

    protected function widths(): array
    {
        return ['A' => 15, 'B' => 15, 'C' => 26, 'D' => 18, 'E' => 18, 'F' => 24, 'G' => 24,
            'H' => 13, 'I' => 16, 'J' => 18, 'K' => 11];
    }

    private function date($value): string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : '-';
    }
}
