<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class InboundReceiptsSummarySheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
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
        $transactionCount = $this->transactions->count();
        $approvedCount = $this->transactions->whereIn('status', ['approved', 'finalized'])->count();
        $pendingCount = $transactionCount - $approvedCount;
        $lineCount = $this->transactions->sum(fn ($transaction) => $transaction->items->count());
        $totalQty = $this->transactions->sum(fn ($transaction) => $transaction->items->sum(
            fn ($item) => (int) ($item->qty_received ?? $item->qty ?? 0)
        ));
        $uniqueSku = $this->transactions
            ->flatMap(fn ($transaction) => $transaction->items->pluck('item_id'))
            ->filter()
            ->unique()
            ->count();
        $approvalRate = $transactionCount > 0 ? $approvedCount / $transactionCount : 0;
        $averageQty = $transactionCount > 0 ? $totalQty / $transactionCount : 0;

        return [
            ['LAPORAN PENERIMAAN BARANG'],
            ['Dibuat pada', now()->format('d/m/Y H:i'), 'Oleh', $this->safeText($this->requestedBy, '-')],
            [''],
            ['INFORMASI LAPORAN'],
            ['Periode', $this->periodLabel()],
            ['Pencarian', $this->safeText($this->filters['q'] ?? null, 'Semua data')],
            ['Status', $this->filterStatusLabel()],
            [''],
            ['INDIKATOR UTAMA', 'NILAI', 'KETERANGAN'],
            ['Total transaksi', $transactionCount, 'Jumlah dokumen penerimaan'],
            ['Total kuantitas diterima', $totalQty, 'Akumulasi qty seluruh item'],
            ['SKU unik', $uniqueSku, 'Jumlah produk berbeda yang diterima'],
            ['Baris item', $lineCount, 'Jumlah detail item pada seluruh transaksi'],
            ['Rata-rata qty / transaksi', $averageQty, 'Total qty dibagi total transaksi'],
            ['Tingkat persetujuan', $approvalRate, 'Persentase transaksi yang sudah disetujui'],
            [''],
            ['DISTRIBUSI STATUS', 'TRANSAKSI', 'PERSENTASE'],
            ['Disetujui', $approvedCount, $transactionCount > 0 ? $approvedCount / $transactionCount : 0],
            ['Menunggu', $pendingCount, $transactionCount > 0 ? $pendingCount / $transactionCount : 0],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 31, 'B' => 24, 'C' => 48, 'D' => 24];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:D1');
                $sheet->getRowDimension(1)->setRowHeight(34);
                $sheet->getStyle('A1:D1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                foreach ([4, 9, 17] as $row) {
                    $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F75B5']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                $sheet->getStyle('A4:D19')->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2F3']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                ]);
                $sheet->getStyle('B10:B13')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('B14')->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('B15')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                $sheet->getStyle('B18:B19')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('C18:C19')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                $sheet->getStyle('A10:A15')->getFont()->setBold(true);
                $sheet->freezePane('A4');
                $sheet->setShowGridlines(false);
            },
        ];
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
            'approved' => 'Disetujui',
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
