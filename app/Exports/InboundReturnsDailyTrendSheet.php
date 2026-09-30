<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InboundReturnsDailyTrendSheet extends InboundReturnsTableSheet implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithTitle
{
    protected string $lastColumn = 'P';

    public function __construct(private Collection $transactions)
    {
    }

    public function title(): string
    {
        return 'Tren Harian';
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Hari',
            'Total Transaksi',
            'Menunggu',
            'Gudang Retur',
            'Finalisasi',
            'Tingkat Finalisasi (%)',
            'SKU Unik',
            'Baris Item',
            'Qty Resi',
            'Qty Diterima',
            'Selisih',
            'Tingkat Penerimaan (%)',
            'Qty Bagus',
            'Qty Rusak',
            'Tingkat Kerusakan (%)',
        ];
    }

    public function collection(): Collection
    {
        return $this->transactions
            ->filter(fn ($transaction) => $transaction->transacted_at !== null)
            ->groupBy(fn ($transaction) => $transaction->transacted_at->format('Y-m-d'))
            ->sortKeys()
            ->map(function (Collection $transactions, string $date) {
                $items = $transactions->flatMap->items;
                $total = $transactions->count();
                $expected = (int) $items->sum(fn ($item) => $this->qtyExpected($item));
                $received = (int) $items->sum(fn ($item) => $this->qtyReceived($item));
                $damaged = (int) $items->sum(fn ($item) => (int) ($item->qty_damaged ?? 0));
                $finalized = $transactions->where('status', 'finalized')->count();

                return [
                    $date,
                    $this->dayName((int) $transactions->first()->transacted_at->dayOfWeek),
                    $total,
                    $transactions->where('status', 'pending')->count(),
                    $transactions->where('status', 'approved')->count(),
                    $finalized,
                    $this->ratio($finalized, $total),
                    $items->pluck('item_id')->filter()->unique()->count(),
                    $items->count(),
                    $expected,
                    $received,
                    (int) $items->sum(fn ($item) => (int) ($item->qty_difference ?? 0)),
                    $this->ratio($received, $expected),
                    (int) $items->sum(fn ($item) => (int) ($item->qty_good ?? 0)),
                    $damaged,
                    $this->ratio($damaged, $received),
                ];
            })
            ->values();
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16, 'B' => 15, 'C' => 18, 'D' => 14, 'E' => 17, 'F' => 14,
            'G' => 21, 'H' => 13, 'I' => 14, 'J' => 13, 'K' => 15, 'L' => 11,
            'M' => 21, 'N' => 12, 'O' => 12, 'P' => 20,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => '#,##0', 'D' => '#,##0', 'E' => '#,##0', 'F' => '#,##0',
            'G' => '0.00%', 'H' => '#,##0', 'I' => '#,##0', 'J' => '#,##0',
            'K' => '#,##0', 'L' => '#,##0', 'M' => '0.00%', 'N' => '#,##0',
            'O' => '#,##0', 'P' => '0.00%',
        ];
    }

    protected function afterTableStyled(Worksheet $sheet, int $highestRow): void
    {
        if ($highestRow >= 2) {
            $sheet->getStyle("C2:P{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    private function dayName(int $dayOfWeek): string
    {
        return ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$dayOfWeek] ?? '-';
    }
}
