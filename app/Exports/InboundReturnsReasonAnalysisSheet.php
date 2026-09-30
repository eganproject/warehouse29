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

class InboundReturnsReasonAnalysisSheet extends InboundReturnsTableSheet implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithTitle
{
    protected string $lastColumn = 'M';

    public function __construct(private Collection $transactions)
    {
    }

    public function title(): string
    {
        return 'Analisis Penyebab';
    }

    public function headings(): array
    {
        return [
            'Peringkat',
            'Penyebab Retur',
            'Jumlah Transaksi',
            'SKU Unik',
            'Baris Item',
            'Qty Resi',
            'Qty Diterima',
            'Selisih',
            'Qty Bagus',
            'Qty Rusak',
            'Tingkat Penerimaan (%)',
            'Tingkat Kerusakan (%)',
            'Kontribusi Qty Diterima (%)',
        ];
    }

    public function collection(): Collection
    {
        $items = $this->transactions->flatMap(function ($transaction) {
            return $transaction->items->map(fn ($item) => [
                'reason_key' => $item->return_reason_id ?: 'unassigned',
                'reason' => $item->returnReason?->name ?: 'Belum ditentukan',
                'transaction_id' => $transaction->id,
                'item_id' => $item->item_id,
                'expected' => $this->qtyExpected($item),
                'received' => $this->qtyReceived($item),
                'difference' => (int) ($item->qty_difference ?? 0),
                'good' => (int) ($item->qty_good ?? 0),
                'damaged' => (int) ($item->qty_damaged ?? 0),
            ]);
        });
        $totalReceived = (int) $items->sum('received');

        return $items
            ->groupBy('reason_key')
            ->map(function (Collection $rows) use ($totalReceived) {
                $expected = (int) $rows->sum('expected');
                $received = (int) $rows->sum('received');
                $damaged = (int) $rows->sum('damaged');

                return [
                    'reason' => $this->safeText($rows->first()['reason'] ?? null, 'Belum ditentukan'),
                    'transactions' => $rows->pluck('transaction_id')->unique()->count(),
                    'skus' => $rows->pluck('item_id')->filter()->unique()->count(),
                    'lines' => $rows->count(),
                    'expected' => $expected,
                    'received' => $received,
                    'difference' => (int) $rows->sum('difference'),
                    'good' => (int) $rows->sum('good'),
                    'damaged' => $damaged,
                    'receive_rate' => $this->ratio($received, $expected),
                    'damage_rate' => $this->ratio($damaged, $received),
                    'contribution' => $this->ratio($received, $totalReceived),
                ];
            })
            ->sortByDesc('received')
            ->values()
            ->map(fn (array $row, int $index) => [
                $index + 1,
                $row['reason'],
                $row['transactions'],
                $row['skus'],
                $row['lines'],
                $row['expected'],
                $row['received'],
                $row['difference'],
                $row['good'],
                $row['damaged'],
                $row['receive_rate'],
                $row['damage_rate'],
                $row['contribution'],
            ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 11, 'B' => 30, 'C' => 18, 'D' => 13, 'E' => 14, 'F' => 13,
            'G' => 15, 'H' => 11, 'I' => 12, 'J' => 12, 'K' => 21, 'L' => 20,
            'M' => 26,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '#,##0', 'C' => '#,##0', 'D' => '#,##0', 'E' => '#,##0',
            'F' => '#,##0', 'G' => '#,##0', 'H' => '#,##0', 'I' => '#,##0',
            'J' => '#,##0', 'K' => '0.00%', 'L' => '0.00%', 'M' => '0.00%',
        ];
    }

    protected function afterTableStyled(Worksheet $sheet, int $highestRow): void
    {
        if ($highestRow >= 2) {
            $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C2:M{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }
}
