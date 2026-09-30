<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class InboundReturnsTableSheet implements WithEvents, WithStrictNullComparison, WithStyles
{
    protected string $lastColumn = 'A';

    public function styles(Worksheet $sheet): array
    {
        return [1 => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $highestRow = max(1, $sheet->getHighestRow());
            $range = "A1:{$this->lastColumn}{$highestRow}";

            $sheet->freezePane('A2');
            $sheet->setAutoFilter($range);
            $sheet->getRowDimension(1)->setRowHeight(34);
            $sheet->getStyle($range)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2F3']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            ]);

            for ($row = 2; $row <= $highestRow; $row++) {
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:{$this->lastColumn}{$row}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7FAFC');
                }
            }

            $this->afterTableStyled($sheet, $highestRow);
        }];
    }

    protected function afterTableStyled(Worksheet $sheet, int $highestRow): void
    {
    }

    protected function safeText(mixed $value, string $fallback = ''): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return $fallback;
        }

        return $text !== '-' && preg_match('/^[=+\-@]/', $text) ? "'{$text}" : $text;
    }

    protected function statusLabel(?string $status): string
    {
        return match ($status) {
            'finalized' => 'Finalisasi',
            'approved' => 'Gudang Retur',
            default => 'Menunggu',
        };
    }

    protected function qtyExpected($item): int
    {
        return (int) ($item->qty_resi ?? $item->qty_received ?? $item->qty ?? 0);
    }

    protected function qtyReceived($item): int
    {
        return (int) ($item->qty_received ?? $item->qty ?? 0);
    }

    protected function ratio(int|float $value, int|float $total): float
    {
        return $total > 0 ? $value / $total : 0;
    }
}
