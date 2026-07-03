<?php

namespace App\Exports;

use App\Models\InboundItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InboundReturnsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithColumnFormatting, WithEvents, WithTitle
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        $query = InboundItem::query()
            ->with([
                'transaction.resi.kurir',
                'transaction.creator',
                'item',
                'returnReason',
            ])
            ->whereHas('transaction', function ($transactionQ) {
                $transactionQ->where('type', 'return');
                $this->applyTransactionFilters($transactionQ);
            })
            ->orderBy(
                \App\Models\InboundTransaction::select('transacted_at')
                    ->whereColumn('inbound_transactions.id', 'inbound_items.inbound_transaction_id'),
                'desc'
            )
            ->orderBy('inbound_items.id');

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Kode Retur',
            'Tanggal',
            'Status',
            'Ref No',
            'No Resi',
            'ID Pesanan',
            'Kurir',
            'SKU',
            'Nama Item',
            'Qty Resi',
            'Qty Diterima',
            'Selisih',
            'Qty Bagus',
            'Qty Rusak',
            'Penyebab',
            'Catatan Penyebab',
            'Catatan Item',
            'Catatan Retur',
            'Submit By',
        ];
    }

    public function map($row): array
    {
        $tx = $row->transaction;
        $resi = $tx?->resi;

        return [
            $tx?->code ?? '-',
            $tx?->transacted_at ? Carbon::parse($tx->transacted_at)->format('Y-m-d H:i') : '-',
            $this->statusLabel($tx?->status ?? 'pending'),
            $tx?->ref_no ?? '-',
            $tx?->return_resi_no ?: ($resi?->no_resi ?: '-'),
            $resi?->id_pesanan ?? '-',
            $resi?->kurir?->name ?? '-',
            $row->item?->sku ?? '-',
            $row->item?->name ?? '-',
            (int) ($row->qty_resi ?? $row->qty_received ?? $row->qty ?? 0),
            (int) ($row->qty_received ?? $row->qty ?? 0),
            (int) ($row->qty_difference ?? 0),
            (int) ($row->qty_good ?? 0),
            (int) ($row->qty_damaged ?? 0),
            $row->returnReason?->name ?? '-',
            $row->return_reason_note ?? '',
            $row->note ?? '',
            $tx?->note ?? '',
            $tx?->creator?->name ?? '-',
        ];
    }

    public function title(): string
    {
        return 'Retur Inbound';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 24,
            'B' => 18,
            'C' => 16,
            'D' => 18,
            'E' => 22,
            'F' => 20,
            'G' => 18,
            'H' => 18,
            'I' => 34,
            'J' => 12,
            'K' => 14,
            'L' => 12,
            'M' => 12,
            'N' => 12,
            'O' => 24,
            'P' => 32,
            'Q' => 30,
            'R' => 34,
            'S' => 20,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'J' => NumberFormat::FORMAT_NUMBER,
            'K' => NumberFormat::FORMAT_NUMBER,
            'L' => NumberFormat::FORMAT_NUMBER,
            'M' => NumberFormat::FORMAT_NUMBER,
            'N' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F2937'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = max(1, $sheet->getHighestRow());
                $highestColumn = $sheet->getHighestColumn();
                $range = "A1:{$highestColumn}{$highestRow}";

                $sheet->freezePane('A2');
                $sheet->setAutoFilter($range);
                $sheet->getRowDimension(1)->setRowHeight(24);

                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_TOP,
                        'wrapText' => true,
                    ],
                ]);

                if ($highestRow >= 2) {
                    $sheet->getStyle("J2:N{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("A2:H{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                    for ($row = 2; $row <= $highestRow; $row++) {
                        if ((int) $sheet->getCell("L{$row}")->getValue() > 0) {
                            $sheet->getStyle("L{$row}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => '92400E']],
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'FEF3C7'],
                                ],
                            ]);
                        }

                        if ((int) $sheet->getCell("N{$row}")->getValue() > 0) {
                            $sheet->getStyle("N{$row}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => '991B1B']],
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'FEE2E2'],
                                ],
                            ]);
                        }
                    }
                }
            },
        ];
    }

    private function applyTransactionFilters($query): void
    {
        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('ref_no', 'like', "%{$search}%")
                    ->orWhere('return_resi_no', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('no_resi', 'like', "%{$search}%")
                            ->orWhere('id_pesanan', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $status = trim((string) ($this->filters['status'] ?? ''));
        if (in_array($status, ['pending', 'approved', 'finalized'], true)) {
            $query->where('status', $status);
        }

        try {
            if (!empty($this->filters['date_from'])) {
                $query->where('transacted_at', '>=', Carbon::parse($this->filters['date_from'])->startOfDay());
            }
            if (!empty($this->filters['date_to'])) {
                $query->where('transacted_at', '<=', Carbon::parse($this->filters['date_to'])->endOfDay());
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'finalized' => 'Finalisasi',
            'approved' => 'Gudang Retur',
            default => 'Menunggu',
        };
    }
}
