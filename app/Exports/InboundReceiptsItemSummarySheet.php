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

class InboundReceiptsItemSummarySheet extends InboundReceiptsTableSheet implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithTitle
{
    protected string $lastColumn = 'J';

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
            'Total Qty Diterima',
            'Kontribusi Qty (%)',
            'Rata-rata Qty / Transaksi',
            'Penerimaan Pertama',
            'Penerimaan Terakhir',
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
                'qty' => $this->itemQty($item),
            ]);
        });
        $totalQty = (int) $items->sum('qty');

        return $items
            ->groupBy('key')
            ->map(function (Collection $rows) use ($transactionCount, $totalQty) {
                $transactions = $rows->pluck('transaction_id')->unique()->count();
                $qty = (int) $rows->sum('qty');

                return [
                    'sku' => $this->safeText($rows->first()['sku'] ?? null, '-'),
                    'name' => $this->safeText($rows->first()['name'] ?? null, '-'),
                    'transactions' => $transactions,
                    'frequency' => $transactionCount > 0 ? $transactions / $transactionCount : 0,
                    'qty' => $qty,
                    'contribution' => $totalQty > 0 ? $qty / $totalQty : 0,
                    'average' => $transactions > 0 ? $qty / $transactions : 0,
                    'first' => $rows->min('transacted_at'),
                    'last' => $rows->max('transacted_at'),
                ];
            })
            ->sortByDesc('qty')
            ->values()
            ->map(fn (array $row, int $index) => [
                $index + 1,
                $row['sku'],
                $row['name'],
                $row['transactions'],
                $row['frequency'],
                $row['qty'],
                $row['contribution'],
                $row['average'],
                $row['first']?->format('Y-m-d') ?? '-',
                $row['last']?->format('Y-m-d') ?? '-',
            ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 11,
            'B' => 19,
            'C' => 38,
            'D' => 18,
            'E' => 22,
            'F' => 19,
            'G' => 20,
            'H' => 25,
            'I' => 20,
            'J' => 20,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'D' => '#,##0',
            'E' => NumberFormat::FORMAT_PERCENTAGE_00,
            'F' => '#,##0',
            'G' => NumberFormat::FORMAT_PERCENTAGE_00,
            'H' => '#,##0.00',
        ];
    }

    protected function afterTableStyled(Worksheet $sheet, int $highestRow): void
    {
        if ($highestRow >= 2) {
            $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D2:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }
}
