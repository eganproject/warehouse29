<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InboundReceiptsDailyTrendSheet extends InboundReceiptsTableSheet implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithTitle
{
    protected string $lastColumn = 'I';

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
            'Disetujui',
            'Menunggu',
            'SKU Unik',
            'Baris Item',
            'Total Qty Diterima',
            'Rata-rata Qty / Transaksi',
        ];
    }

    public function collection(): Collection
    {
        return $this->transactions
            ->filter(fn ($transaction) => $transaction->transacted_at !== null)
            ->groupBy(fn ($transaction) => $transaction->transacted_at->format('Y-m-d'))
            ->sortKeys()
            ->map(function (Collection $transactions, string $date) {
                $total = $transactions->count();
                $approved = $transactions->whereIn('status', ['approved', 'finalized'])->count();
                $qty = $transactions->sum(fn ($transaction) => $transaction->items->sum(
                    fn ($item) => $this->itemQty($item)
                ));

                return [
                    $date,
                    $this->dayName((int) $transactions->first()->transacted_at->dayOfWeek),
                    $total,
                    $approved,
                    $total - $approved,
                    $transactions->flatMap(fn ($transaction) => $transaction->items->pluck('item_id'))->filter()->unique()->count(),
                    $transactions->sum(fn ($transaction) => $transaction->items->count()),
                    $qty,
                    $total > 0 ? $qty / $total : 0,
                ];
            })
            ->values();
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 15,
            'C' => 18,
            'D' => 15,
            'E' => 15,
            'F' => 14,
            'G' => 15,
            'H' => 20,
            'I' => 27,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => '#,##0',
            'D' => '#,##0',
            'E' => '#,##0',
            'F' => '#,##0',
            'G' => '#,##0',
            'H' => '#,##0',
            'I' => '#,##0.00',
        ];
    }

    protected function afterTableStyled(Worksheet $sheet, int $highestRow): void
    {
        if ($highestRow >= 2) {
            $sheet->getStyle("C2:I{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    private function dayName(int $dayOfWeek): string
    {
        return ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$dayOfWeek] ?? '-';
    }
}
