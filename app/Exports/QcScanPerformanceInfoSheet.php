<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class QcScanPerformanceInfoSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(private array $filters, private string $generatedBy) {}

    public function title(): string
    {
        return 'Petunjuk Laporan';
    }

    public function array(): array
    {
        return [
            ['LAPORAN PERFORMA QC SCAN', ''],
            ['Dibuat pada', now()->format('d-m-Y H:i')],
            ['Dibuat oleh', $this->generatedBy],
            ['Periode', $this->periodLabel()],
            ['Filter petugas', $this->filters['q'] ?: 'Semua petugas'],
            ['', ''],
            ['DEFINISI METRIK', ''],
            ['Jam aktif', 'Jumlah blok jam yang memiliki minimal satu resi QC scan.'],
            ['Resi/jam aktif', 'Total resi dibagi jam aktif; mengukur throughput pada jam yang benar-benar memiliki aktivitas.'],
            ['Rata-rata durasi', 'Rata-rata waktu dari scan awal sampai QC selesai, hanya untuk resi selesai dengan waktu yang valid.'],
            ['Penyelesaian', 'Persentase resi berstatus selesai dibanding seluruh resi yang discan.'],
            ['Pemenuhan qty', 'Persentase total qty discan dibanding total qty wajib.'],
            ['', ''],
            ['ISI WORKBOOK', ''],
            ['Ringkasan Harian', 'Performa setiap akun QC per hari.'],
            ['Performa Per Jam', 'Breakdown produktivitas akun QC di setiap blok jam.'],
            ['Detail Resi', 'Data tingkat resi untuk audit dan analisis lanjutan.'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:B1');
                $sheet->mergeCells('A7:B7');
                $sheet->mergeCells('A14:B14');
                foreach ([1, 7, 14] as $row) {
                    $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => $row === 1 ? 15 : 11],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $row === 1 ? '1D4ED8' : '334155']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight($row === 1 ? 28 : 22);
                }
                $sheet->getColumnDimension('A')->setWidth(24);
                $sheet->getColumnDimension('B')->setWidth(90);
                $sheet->getStyle('A1:B17')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->freezePane('A2');
            },
        ];
    }

    private function periodLabel(): string
    {
        try {
            $from = ! empty($this->filters['date_from']) ? Carbon::parse($this->filters['date_from'])->format('d-m-Y') : 'awal data';
            $to = ! empty($this->filters['date_to']) ? Carbon::parse($this->filters['date_to'])->format('d-m-Y') : 'akhir data';

            return $from.' s.d. '.$to;
        } catch (\Throwable) {
            return 'Sesuai filter laporan';
        }
    }
}
