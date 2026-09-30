<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class InboundReturnsSummarySheet implements FromArray, WithColumnWidths, WithEvents, WithStrictNullComparison, WithTitle
{
    public function __construct(
        private Collection $transactions,
        private array $filters = [],
        private string $requestedBy = '-'
    ) {
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function array(): array
    {
        $items = $this->transactions->flatMap->items;
        $transactionCount = $this->transactions->count();
        $pending = $this->transactions->where('status', 'pending')->count();
        $approved = $this->transactions->where('status', 'approved')->count();
        $finalized = $this->transactions->where('status', 'finalized')->count();
        $expected = (int) $items->sum(fn ($item) => $this->qtyExpected($item));
        $received = (int) $items->sum(fn ($item) => $this->qtyReceived($item));
        $difference = (int) $items->sum(fn ($item) => (int) ($item->qty_difference ?? 0));
        $good = (int) $items->sum(fn ($item) => (int) ($item->qty_good ?? 0));
        $damaged = (int) $items->sum(fn ($item) => (int) ($item->qty_damaged ?? 0));
        $invalidLines = $items->filter(function ($item) {
            $expected = $this->qtyExpected($item);
            $received = $this->qtyReceived($item);

            return $received !== (int) ($item->qty_good ?? 0) + (int) ($item->qty_damaged ?? 0)
                || (int) ($item->qty_difference ?? 0) !== max($expected - $received, 0);
        })->count();
        $finalizationHours = $this->transactions
            ->filter(fn ($transaction) => $transaction->transacted_at && $transaction->finalized_at)
            ->map(fn ($transaction) => $transaction->transacted_at->diffInMinutes($transaction->finalized_at) / 60);
        $averageFinalizationHours = $finalizationHours->isNotEmpty() ? $finalizationHours->avg() : 0;

        return [
            ['LAPORAN ANALITIK RETUR INBOUND'],
            ['Dibuat pada', now()->format('d/m/Y H:i'), 'Oleh', $this->safeText($this->requestedBy, '-')],
            [''],
            ['INFORMASI LAPORAN'],
            ['Periode', $this->periodLabel()],
            ['Pencarian', $this->safeText($this->filters['q'] ?? null, 'Semua data')],
            ['Status', $this->filterStatusLabel()],
            ['Cakupan', 'Semua baris item dari transaksi yang sesuai filter halaman'],
            [''],
            ['INDIKATOR UTAMA', 'NILAI', 'KETERANGAN'],
            ['Total transaksi', $transactionCount, 'Jumlah dokumen retur inbound'],
            ['Baris item', $items->count(), 'Jumlah detail item pada seluruh transaksi'],
            ['SKU unik', $items->pluck('item_id')->filter()->unique()->count(), 'Jumlah produk berbeda'],
            ['Qty pada resi', $expected, 'Qty yang seharusnya kembali menurut resi'],
            ['Qty diterima', $received, 'Qty fisik yang diterima gudang'],
            ['Selisih qty', $difference, 'Qty pada resi yang belum diterima'],
            ['Tingkat penerimaan', $this->ratio($received, $expected), 'Qty diterima dibanding qty pada resi'],
            ['Qty bagus', $good, 'Qty diterima dalam kondisi bagus'],
            ['Qty rusak', $damaged, 'Qty diterima dalam kondisi rusak'],
            ['Tingkat barang bagus', $this->ratio($good, $received), 'Qty bagus dibanding qty diterima'],
            ['Tingkat kerusakan', $this->ratio($damaged, $received), 'Qty rusak dibanding qty diterima'],
            ['Transaksi finalisasi', $finalized, 'Retur yang sudah selesai diproses ke stok'],
            ['Tingkat finalisasi', $this->ratio($finalized, $transactionCount), 'Transaksi finalisasi dibanding seluruh transaksi'],
            ['Rata-rata waktu finalisasi (jam)', $averageFinalizationHours, 'Dari waktu transaksi sampai finalisasi'],
            ['Retur terhubung resi', $this->transactions->whereNotNull('resi_id')->count(), 'Transaksi dengan referensi resi sistem'],
            ['Baris perlu validasi', $invalidLines, 'Komposisi qty atau selisih tidak konsisten'],
            [''],
            ['DISTRIBUSI STATUS', 'TRANSAKSI', 'PERSENTASE'],
            ['Menunggu', $pending, $this->ratio($pending, $transactionCount)],
            ['Gudang Retur', $approved, $this->ratio($approved, $transactionCount)],
            ['Finalisasi', $finalized, $this->ratio($finalized, $transactionCount)],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 34, 'B' => 27, 'C' => 53, 'D' => 25];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:D1');
                $sheet->getRowDimension(1)->setRowHeight(36);
                $sheet->getStyle('A1:D1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                foreach ([4, 10, 28] as $row) {
                    $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F75B5']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                $sheet->getStyle('A4:D31')->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2F3']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                ]);
                $sheet->getStyle('B11:B16')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('B17')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                $sheet->getStyle('B18:B19')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('B20:B21')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                $sheet->getStyle('B22')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('B23')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                $sheet->getStyle('B24')->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('B25:B26')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('B29:B31')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('C29:C31')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                $sheet->getStyle('A11:A26')->getFont()->setBold(true);

                if ((int) $sheet->getCell('B26')->getValue() > 0) {
                    $sheet->getStyle('A26:C26')->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '9C0006']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC7CE']],
                    ]);
                }

                $sheet->freezePane('A4');
                $sheet->setShowGridlines(false);
            },
        ];
    }

    private function qtyExpected($item): int
    {
        return (int) ($item->qty_resi ?? $item->qty_received ?? $item->qty ?? 0);
    }

    private function qtyReceived($item): int
    {
        return (int) ($item->qty_received ?? $item->qty ?? 0);
    }

    private function ratio(int|float $value, int|float $total): float
    {
        return $total > 0 ? $value / $total : 0;
    }

    private function periodLabel(): string
    {
        $from = $this->filters['date_from'] ?? null;
        $to = $this->filters['date_to'] ?? null;

        if (!$from && !$to && $this->transactions->isNotEmpty()) {
            $from = $this->transactions->min('transacted_at');
            $to = $this->transactions->max('transacted_at');
        }

        $fromLabel = $from ? Carbon::parse($from)->format('d/m/Y') : 'Awal data';
        $toLabel = $to ? Carbon::parse($to)->format('d/m/Y') : 'Akhir data';

        return "{$fromLabel} s/d {$toLabel}";
    }

    private function filterStatusLabel(): string
    {
        return match ($this->filters['status'] ?? '') {
            'pending' => 'Menunggu',
            'approved' => 'Gudang Retur',
            'finalized' => 'Finalisasi',
            default => 'Semua status',
        };
    }

    private function safeText(mixed $value, string $fallback): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return $fallback;
        }

        return $text !== '-' && preg_match('/^[=+\-@]/', $text) ? "'{$text}" : $text;
    }
}
