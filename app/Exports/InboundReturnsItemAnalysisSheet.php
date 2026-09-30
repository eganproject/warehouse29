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

class InboundReturnsItemAnalysisSheet extends InboundReturnsTableSheet implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithTitle
{
    protected string $lastColumn = 'P';

    public function __construct(private Collection $transactions)
    {
    }

    public function title(): string
    {
        return 'Analisis SKU';
    }

    public function headings(): array
    {
        return [
            'Peringkat',
            'SKU',
            'Nama Item',
            'Jumlah Transaksi',
            'Frekuensi Transaksi (%)',
            'Qty Resi',
            'Qty Diterima',
            'Selisih',
            'Tingkat Penerimaan (%)',
            'Qty Bagus',
            'Qty Rusak',
            'Tingkat Kerusakan (%)',
            'Kontribusi Qty Diterima (%)',
            'Penyebab Dominan',
            'Retur Pertama',
            'Retur Terakhir',
        ];
    }

    public function collection(): Collection
    {
        $transactionCount = $this->transactions->count();
        $items = $this->transactions->flatMap(function ($transaction) {
            return $transaction->items->map(fn ($item) => [
                'key' => $item->item_id ?: 'missing-'.$item->id,
                'transaction_id' => $transaction->id,
                'transacted_at' => $transaction->transacted_at,
                'sku' => $item->item?->sku,
                'name' => $item->item?->name,
                'expected' => $this->qtyExpected($item),
                'received' => $this->qtyReceived($item),
                'difference' => (int) ($item->qty_difference ?? 0),
                'good' => (int) ($item->qty_good ?? 0),
                'damaged' => (int) ($item->qty_damaged ?? 0),
                'reason' => $item->returnReason?->name ?: 'Belum ditentukan',
            ]);
        });
        $totalReceived = (int) $items->sum('received');

        return $items
            ->groupBy('key')
            ->map(function (Collection $rows) use ($transactionCount, $totalReceived) {
                $transactions = $rows->pluck('transaction_id')->unique()->count();
                $expected = (int) $rows->sum('expected');
                $received = (int) $rows->sum('received');
                $damaged = (int) $rows->sum('damaged');
                $dominantReason = $rows
                    ->groupBy('reason')
                    ->map(fn (Collection $reasonRows) => (int) $reasonRows->sum('received'))
                    ->sortDesc()
                    ->keys()
                    ->first();

                return [
                    'sku' => $this->safeText($rows->first()['sku'] ?? null, '-'),
                    'name' => $this->safeText($rows->first()['name'] ?? null, '-'),
                    'transactions' => $transactions,
                    'frequency' => $this->ratio($transactions, $transactionCount),
                    'expected' => $expected,
                    'received' => $received,
                    'difference' => (int) $rows->sum('difference'),
                    'receive_rate' => $this->ratio($received, $expected),
                    'good' => (int) $rows->sum('good'),
                    'damaged' => $damaged,
                    'damage_rate' => $this->ratio($damaged, $received),
                    'contribution' => $this->ratio($received, $totalReceived),
                    'reason' => $this->safeText($dominantReason, 'Belum ditentukan'),
                    'first' => $rows->min('transacted_at'),
                    'last' => $rows->max('transacted_at'),
                ];
            })
            ->sortByDesc('received')
            ->values()
            ->map(fn (array $row, int $index) => [
                $index + 1,
                $row['sku'],
                $row['name'],
                $row['transactions'],
                $row['frequency'],
                $row['expected'],
                $row['received'],
                $row['difference'],
                $row['receive_rate'],
                $row['good'],
                $row['damaged'],
                $row['damage_rate'],
                $row['contribution'],
                $row['reason'],
                $row['first']?->format('Y-m-d') ?? '-',
                $row['last']?->format('Y-m-d') ?? '-',
            ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 11, 'B' => 19, 'C' => 38, 'D' => 18, 'E' => 22, 'F' => 13,
            'G' => 15, 'H' => 11, 'I' => 21, 'J' => 12, 'K' => 12, 'L' => 20,
            'M' => 26, 'N' => 25, 'O' => 18, 'P' => 18,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '#,##0', 'D' => '#,##0', 'E' => '0.00%', 'F' => '#,##0',
            'G' => '#,##0', 'H' => '#,##0', 'I' => '0.00%', 'J' => '#,##0',
            'K' => '#,##0', 'L' => '0.00%', 'M' => '0.00%',
        ];
    }

    protected function afterTableStyled(Worksheet $sheet, int $highestRow): void
    {
        if ($highestRow >= 2) {
            $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D2:M{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }
}
