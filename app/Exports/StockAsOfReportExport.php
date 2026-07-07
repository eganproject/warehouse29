<?php

namespace App\Exports;

use App\Http\Controllers\Admin\StockAsOfReportController;
use App\Models\Category;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockAsOfReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithStyles, WithEvents
{
    private array $summary = [];

    public function __construct(private array $filters)
    {
    }

    public function collection(): Collection
    {
        $controller = app(StockAsOfReportController::class);
        $this->summary = $controller->summaryForExport($this->filters);

        return $controller->rowsForExport($this->filters);
    }

    public function startCell(): string
    {
        return 'A8';
    }

    public function headings(): array
    {
        return [
            'No',
            'SKU',
            'Nama Item',
            'Tipe',
            'Kategori',
            'Stok Reguler',
            'Stok Rusak',
            'Total Stok',
            'Safety Stock',
            'Gap Safety',
            'Status',
            'Total IN s/d Tanggal',
            'Total OUT s/d Tanggal',
            'Mutasi Terakhir',
            'Alamat',
        ];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $row['sku'],
            $row['name'],
            $row['item_type'],
            $row['category'],
            $row['stock_as_of'],
            $row['damaged_stock_as_of'],
            $row['total_stock_as_of'],
            $row['safety_stock'],
            $row['gap'],
            $row['status'],
            $row['inbound_as_of'],
            $row['outbound_as_of'],
            $row['last_mutation_at'],
            $row['address'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            8 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F2937']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->mergeCells('A1:O1');
                $sheet->setCellValue('A1', 'Laporan Stok Per Tanggal');
                $sheet->setCellValue('A3', 'Tanggal Posisi');
                $sheet->setCellValue('B3', $this->filters['as_of_date'] ?? '');
                $sheet->setCellValue('D3', 'Dibuat Pada');
                $sheet->setCellValue('E3', now()->format('Y-m-d H:i'));

                $sheet->setCellValue('A4', 'Pencarian');
                $sheet->setCellValue('B4', $this->filters['q'] !== '' ? $this->filters['q'] : 'Semua');
                $sheet->setCellValue('D4', 'Kategori');
                $sheet->setCellValue('E4', $this->categoryLabel());

                $sheet->setCellValue('A5', 'Status');
                $sheet->setCellValue('B5', $this->statusLabel());
                $sheet->setCellValue('D5', 'Jenis Filter Stok');
                $sheet->setCellValue('E5', $this->stockTypeLabel());

                $sheet->setCellValue('A6', 'Total SKU');
                $sheet->setCellValue('B6', $this->summary['total_sku'] ?? 0);
                $sheet->setCellValue('D6', 'Total Reguler');
                $sheet->setCellValue('E6', $this->summary['total_regular'] ?? 0);
                $sheet->setCellValue('G6', 'Total Rusak');
                $sheet->setCellValue('H6', $this->summary['total_damaged'] ?? 0);
                $sheet->setCellValue('J6', 'Total Semua');
                $sheet->setCellValue('K6', $this->summary['total_all'] ?? 0);

                $sheet->freezePane('A9');
                $sheet->setAutoFilter('A8:O'.$highestRow);
                $sheet->getStyle('A8:O'.$highestRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('F9:M'.$highestRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('A8:O'.$highestRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle('A9:A'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('F9:M'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }

    private function categoryLabel(): string
    {
        $categoryId = $this->filters['category_id'] ?? '';
        if ($categoryId === '' || $categoryId === null) {
            return 'Semua';
        }
        if ((int) $categoryId === 0) {
            return 'Tanpa Kategori';
        }

        return Category::whereKey((int) $categoryId)->value('name') ?? 'Kategori '.$categoryId;
    }

    private function statusLabel(): string
    {
        return match ($this->filters['status'] ?? '') {
            'positive' => 'Stok > 0',
            'zero' => 'Stok = 0',
            'negative' => 'Stok < 0',
            'low' => 'Di bawah safety stock',
            default => 'Semua',
        };
    }

    private function stockTypeLabel(): string
    {
        return match ($this->filters['stock_type'] ?? 'regular') {
            'damaged' => 'Stok Rusak',
            default => 'Stok Reguler',
        };
    }
}
