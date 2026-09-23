<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InboundReceiptsDetailSheet extends InboundReceiptsTableSheet implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithTitle
{
    protected string $lastColumn = 'N';

    public function __construct(private Collection $transactions)
    {
    }

    public function title(): string
    {
        return 'Detail Penerimaan';
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Penerimaan',
            'Tanggal',
            'Status',
            'No Referensi',
            'SKU',
            'Nama Item',
            'Qty Diterima',
            'Catatan Item',
            'Catatan Penerimaan',
            'Submit Oleh',
            'Disetujui Oleh',
            'Waktu Persetujuan',
            'ID Transaksi',
        ];
    }

    public function collection(): Collection
    {
        $number = 0;

        return $this->transactions->flatMap(function ($transaction) use (&$number) {
            return $transaction->items->map(function ($item) use ($transaction, &$number) {
                $number++;

                return [
                    $number,
                    $this->safeText($transaction->code, '-'),
                    $transaction->transacted_at?->format('Y-m-d H:i') ?? '-',
                    $this->statusLabel($transaction->status),
                    $this->safeText($transaction->ref_no, '-'),
                    $this->safeText($item->item?->sku, '-'),
                    $this->safeText($item->item?->name, '-'),
                    $this->itemQty($item),
                    $this->safeText($item->note),
                    $this->safeText($transaction->note),
                    $this->safeText($transaction->creator?->name, '-'),
                    $this->safeText($transaction->approver?->name, '-'),
                    $transaction->approved_at?->format('Y-m-d H:i') ?? '-',
                    (int) $transaction->id,
                ];
            });
        })->values();
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 25,
            'C' => 18,
            'D' => 14,
            'E' => 20,
            'F' => 18,
            'G' => 36,
            'H' => 14,
            'I' => 30,
            'J' => 34,
            'K' => 20,
            'L' => 20,
            'M' => 20,
            'N' => 13,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'H' => '#,##0',
            'N' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    protected function afterTableStyled(Worksheet $sheet, int $highestRow): void
    {
        if ($highestRow < 2) {
            return;
        }

        $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H2:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        for ($row = 2; $row <= $highestRow; $row++) {
            $status = (string) $sheet->getCell("D{$row}")->getValue();
            $color = $status === 'Disetujui' || $status === 'Finalisasi' ? 'E2F0D9' : 'FFF2CC';
            $fontColor = $status === 'Disetujui' || $status === 'Finalisasi' ? '375623' : '7F6000';
            $sheet->getStyle("D{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $fontColor]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }
    }
}
