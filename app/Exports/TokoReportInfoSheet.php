<?php

namespace App\Exports;

use App\Support\TokoFilter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TokoReportInfoSheet implements FromArray, WithEvents, WithTitle
{
    public function __construct(
        private array $filters,
        private array $summary,
        private string $generatedBy
    ) {}

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function array(): array
    {
        return [
            ['LAPORAN RESI PER TOKO', ''],
            ['Dibuat pada', now()->format('d-m-Y H:i')],
            ['Dibuat oleh', $this->generatedBy],
            ['Periode (tanggal upload)', $this->filters['date_from'].' s.d. '.$this->filters['date_to']],
            ['Filter toko', TokoFilter::tokoLabel($this->filters['toko_id'] ?? null)],
            ['Filter channel', TokoFilter::channelLabel($this->filters['channel_id'] ?? null)],
            ['Cari toko', ($this->filters['q'] ?? '') !== '' ? $this->filters['q'] : '-'],
            ['', ''],
            ['RINGKASAN', ''],
            ['Jumlah toko', $this->summary['toko_count']],
            ['Resi aktif', $this->summary['total_resi']],
            ['Sudah scan out', $this->summary['scan_total']],
            ['Sisa belum scan out', $this->summary['remaining']],
            ['Penyelesaian (%)', $this->summary['completion_rate']],
            ['Resi batal', $this->summary['canceled_total']],
            ['Rasio batal (%)', $this->summary['cancel_rate']],
            ['Total qty', $this->summary['total_qty']],
            ['Toko tersibuk', $this->summary['top_toko'].' ('.$this->summary['top_toko_resi'].' resi)'],
            ['Resi tanpa toko', $this->summary['without_toko']],
            ['', ''],
            ['CATATAN', ''],
            ['Resi aktif', 'Resi dengan status selain dibatalkan, berdasarkan tanggal upload.'],
            ['Penyelesaian', 'Resi aktif yang sudah scan out dibagi seluruh resi aktif.'],
            ['Rasio batal', 'Resi batal dibagi (resi aktif + resi batal).'],
            ['Tanpa Toko', 'Resi yang diimport tanpa kolom Nama Toko (termasuk data lama).'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                foreach ([1, 9, 21] as $row) {
                    $sheet->mergeCells("A{$row}:B{$row}");
                    $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => $row === 1 ? 15 : 11],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $row === 1 ? '1D4ED8' : '334155']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight($row === 1 ? 28 : 22);
                }
                $sheet->getColumnDimension('A')->setWidth(26);
                $sheet->getColumnDimension('B')->setWidth(70);
                $sheet->getStyle('A1:B25')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle('B2:B19')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            },
        ];
    }
}
