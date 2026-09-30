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

class InboundReturnsCourierAnalysisSheet extends InboundReturnsTableSheet implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithTitle
{
    protected string $lastColumn = 'N';

    public function __construct(private Collection $transactions)
    {
    }

    public function title(): string
    {
        return 'Analisis Kurir';
    }

    public function headings(): array
    {
        return [
            'Peringkat',
            'Kurir',
            'Jumlah Transaksi',
            'Kontribusi Transaksi (%)',
            'Pesanan Unik',
            'Resi Unik',
            'Qty Resi',
            'Qty Diterima',
            'Selisih',
            'Tingkat Penerimaan (%)',
            'Qty Bagus',
            'Qty Rusak',
            'Tingkat Kerusakan (%)',
            'Kontribusi Qty Diterima (%)',
        ];
    }

    public function collection(): Collection
    {
        $transactionCount = $this->transactions->count();
        $totalReceived = (int) $this->transactions->sum(
            fn ($transaction) => $transaction->items->sum(fn ($item) => $this->qtyReceived($item))
        );

        return $this->transactions
            ->groupBy(fn ($transaction) => $transaction->resi?->kurir_id ?: 'unknown')
            ->map(function (Collection $transactions) use ($transactionCount, $totalReceived) {
                $items = $transactions->flatMap->items;
                $expected = (int) $items->sum(fn ($item) => $this->qtyExpected($item));
                $received = (int) $items->sum(fn ($item) => $this->qtyReceived($item));
                $damaged = (int) $items->sum(fn ($item) => (int) ($item->qty_damaged ?? 0));

                return [
                    'courier' => $this->safeText($transactions->first()->resi?->kurir?->name, 'Tidak diketahui / tanpa resi'),
                    'transactions' => $transactions->count(),
                    'transaction_share' => $this->ratio($transactions->count(), $transactionCount),
                    'orders' => $transactions->pluck('resi.id_pesanan')->filter()->unique()->count(),
                    'resis' => $transactions->map(
                        fn ($transaction) => $transaction->return_resi_no ?: $transaction->resi?->no_resi
                    )->filter()->unique()->count(),
                    'expected' => $expected,
                    'received' => $received,
                    'difference' => (int) $items->sum(fn ($item) => (int) ($item->qty_difference ?? 0)),
                    'receive_rate' => $this->ratio($received, $expected),
                    'good' => (int) $items->sum(fn ($item) => (int) ($item->qty_good ?? 0)),
                    'damaged' => $damaged,
                    'damage_rate' => $this->ratio($damaged, $received),
                    'contribution' => $this->ratio($received, $totalReceived),
                ];
            })
            ->sortByDesc('received')
            ->values()
            ->map(fn (array $row, int $index) => [
                $index + 1,
                $row['courier'],
                $row['transactions'],
                $row['transaction_share'],
                $row['orders'],
                $row['resis'],
                $row['expected'],
                $row['received'],
                $row['difference'],
                $row['receive_rate'],
                $row['good'],
                $row['damaged'],
                $row['damage_rate'],
                $row['contribution'],
            ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 11, 'B' => 29, 'C' => 18, 'D' => 24, 'E' => 15, 'F' => 13,
            'G' => 13, 'H' => 15, 'I' => 11, 'J' => 21, 'K' => 12, 'L' => 12,
            'M' => 20, 'N' => 26,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '#,##0', 'C' => '#,##0', 'D' => '0.00%', 'E' => '#,##0',
            'F' => '#,##0', 'G' => '#,##0', 'H' => '#,##0', 'I' => '#,##0',
            'J' => '0.00%', 'K' => '#,##0', 'L' => '#,##0', 'M' => '0.00%',
            'N' => '0.00%',
        ];
    }

    protected function afterTableStyled(Worksheet $sheet, int $highestRow): void
    {
        if ($highestRow >= 2) {
            $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C2:N{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }
}
