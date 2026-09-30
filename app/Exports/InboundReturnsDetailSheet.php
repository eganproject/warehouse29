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

class InboundReturnsDetailSheet extends InboundReturnsTableSheet implements FromCollection, WithColumnFormatting, WithColumnWidths, WithHeadings, WithTitle
{
    protected string $lastColumn = 'AB';

    public function __construct(private Collection $transactions)
    {
    }

    public function title(): string
    {
        return 'Detail Retur';
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Retur',
            'Tanggal',
            'Status',
            'No Referensi',
            'No Resi',
            'ID Pesanan',
            'Kurir',
            'SKU',
            'Nama Item',
            'Qty Resi',
            'Qty Diterima',
            'Selisih',
            'Tingkat Penerimaan (%)',
            'Qty Bagus',
            'Qty Rusak',
            'Tingkat Kerusakan (%)',
            'Penyebab',
            'Catatan Penyebab',
            'Catatan Item',
            'Catatan Retur',
            'Submit Oleh',
            'Disetujui Oleh',
            'Waktu Persetujuan',
            'Finalisasi Oleh',
            'Waktu Finalisasi',
            'Validasi Qty',
            'ID Transaksi',
        ];
    }

    public function collection(): Collection
    {
        $number = 0;

        return $this->transactions->flatMap(function ($transaction) use (&$number) {
            return $transaction->items->map(function ($item) use ($transaction, &$number) {
                $number++;
                $expected = $this->qtyExpected($item);
                $received = $this->qtyReceived($item);
                $good = (int) ($item->qty_good ?? 0);
                $damaged = (int) ($item->qty_damaged ?? 0);
                $difference = (int) ($item->qty_difference ?? 0);
                $valid = $received === $good + $damaged
                    && $difference === max($expected - $received, 0);

                return [
                    $number,
                    $this->safeText($transaction->code, '-'),
                    $transaction->transacted_at?->format('Y-m-d H:i') ?? '-',
                    $this->statusLabel($transaction->status),
                    $this->safeText($transaction->ref_no, '-'),
                    $this->safeText($transaction->return_resi_no ?: $transaction->resi?->no_resi, '-'),
                    $this->safeText($transaction->resi?->id_pesanan, '-'),
                    $this->safeText($transaction->resi?->kurir?->name, 'Tidak diketahui'),
                    $this->safeText($item->item?->sku, '-'),
                    $this->safeText($item->item?->name, '-'),
                    $expected,
                    $received,
                    $difference,
                    $this->ratio($received, $expected),
                    $good,
                    $damaged,
                    $this->ratio($damaged, $received),
                    $this->safeText($item->returnReason?->name, 'Belum ditentukan'),
                    $this->safeText($item->return_reason_note),
                    $this->safeText($item->note),
                    $this->safeText($transaction->note),
                    $this->safeText($transaction->creator?->name, '-'),
                    $this->safeText($transaction->approver?->name, '-'),
                    $transaction->approved_at?->format('Y-m-d H:i') ?? '-',
                    $this->safeText($transaction->finalizer?->name, '-'),
                    $transaction->finalized_at?->format('Y-m-d H:i') ?? '-',
                    $valid ? 'Sesuai' : 'Perlu diperiksa',
                    (int) $transaction->id,
                ];
            });
        })->values();
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8, 'B' => 24, 'C' => 18, 'D' => 16, 'E' => 19, 'F' => 22,
            'G' => 20, 'H' => 18, 'I' => 18, 'J' => 35, 'K' => 12, 'L' => 14,
            'M' => 11, 'N' => 21, 'O' => 12, 'P' => 12, 'Q' => 20, 'R' => 24,
            'S' => 30, 'T' => 30, 'U' => 32, 'V' => 20, 'W' => 20, 'X' => 20,
            'Y' => 20, 'Z' => 20, 'AA' => 18, 'AB' => 13,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'K' => '#,##0',
            'L' => '#,##0',
            'M' => '#,##0',
            'N' => NumberFormat::FORMAT_PERCENTAGE_00,
            'O' => '#,##0',
            'P' => '#,##0',
            'Q' => NumberFormat::FORMAT_PERCENTAGE_00,
            'AB' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    protected function afterTableStyled(Worksheet $sheet, int $highestRow): void
    {
        if ($highestRow < 2) {
            return;
        }

        $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("K2:Q{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("AA2:AA{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        for ($row = 2; $row <= $highestRow; $row++) {
            $status = (string) $sheet->getCell("D{$row}")->getValue();
            $statusStyle = match ($status) {
                'Finalisasi' => ['E2F0D9', '375623'],
                'Gudang Retur' => ['DDEBF7', '1F4E78'],
                default => ['FFF2CC', '7F6000'],
            };
            $sheet->getStyle("D{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $statusStyle[1]]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $statusStyle[0]]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            if ((int) $sheet->getCell("M{$row}")->getValue() > 0) {
                $sheet->getStyle("M{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '9C6500']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEB9C']],
                ]);
            }
            if ((int) $sheet->getCell("P{$row}")->getValue() > 0) {
                $sheet->getStyle("P{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '9C0006']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC7CE']],
                ]);
            }
            if ($sheet->getCell("AA{$row}")->getValue() === 'Perlu diperiksa') {
                $sheet->getStyle("AA{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '9C0006']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC7CE']],
                ]);
            }
        }
    }
}
