<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Gaya tabel bersama untuk sheet Laporan Toko: header gelap, border, autofilter, freeze.
 */
abstract class TokoReportTableSheet implements WithEvents, WithHeadings, WithStyles, WithTitle
{
    /** @return array<string,int> */
    abstract protected function widths(): array;

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(1, $sheet->getHighestRow());
                $lastColumn = $sheet->getHighestColumn();
                $range = "A1:{$lastColumn}{$lastRow}";

                foreach ($this->widths() as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                $sheet->freezePane('A2');
                $sheet->setAutoFilter($range);
                $sheet->getRowDimension(1)->setRowHeight(32);
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D8E0EA']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
            },
        ];
    }

    /**
     * Cegah teks dari data import terbaca sebagai formula Excel.
     */
    protected function safeText(mixed $value, string $fallback = '-'): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return $fallback;
        }

        return $text !== '-' && preg_match('/^[=+\-@]/', $text) ? "'{$text}" : $text;
    }
}
