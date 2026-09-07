<?php

namespace App\Exports;

use App\Models\Category;
use App\Support\StockPeriodReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class StockAsOfReportExport extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping
{
    public function __construct(private array $filters) {}

    public function collection(): Collection
    {
        return app(StockPeriodReport::class)->ordered($this->filters)->get();
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function headings(): array
    {
        return $this->filters['tab'] === 'movement'
            ? ['SKU', 'Nama Barang', 'Kategori', 'Alamat', 'Satuan', 'Qty Out', 'Rata-rata Out / Hari', 'Hari Keluar', 'Klasifikasi', 'Stok Akhir', 'Keluar Terakhir']
            : ['SKU', 'Nama Barang', 'Kategori', 'Alamat', 'Satuan', 'Stok Awal', 'Qty In', 'Qty Out', 'Stok Akhir'];
    }

    public function map($row): array
    {
        $identity = [$row->sku, $row->name, $row->category, $row->address, $row->uom];

        return array_merge($identity, $this->filters['tab'] === 'movement'
            ? [(int) $row->qty_out, round((float) $row->average_out, 2), (int) $row->outgoing_days, StockPeriodReport::MOVEMENTS[$row->movement], (int) $row->closing, $row->last_out_at ?? '-']
            : [(int) $row->opening, (int) $row->qty_in, (int) $row->qty_out, (int) $row->closing]);
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastColumn = $this->filters['tab'] === 'movement' ? 'K' : 'I';
            $sheet->mergeCells('A1:'.$lastColumn.'1');
            $sheet->setCellValue('A1', $this->filters['tab'] === 'movement' ? 'Analisis Pergerakan Stok' : 'Saldo Stok per Periode');
            $sheet->mergeCells('A2:'.$lastColumn.'2');
            $sheet->setCellValue('A2', $this->filters['date_from'].' s.d. '.$this->filters['date_to'].' | Stok '.($this->filters['stock_type'] === 'damaged' ? 'rusak' : 'reguler'));
            $sheet->mergeCells('A3:'.$lastColumn.'3');
            $sheet->setCellValue('A3', 'SKU fisik aktif; berdasarkan mutasi tercatat. Stok akhir = stok awal + qty in - qty out.');
            $sheet->mergeCells('A4:'.$lastColumn.'4');
            $sheet->setCellValue('A4', $this->filters['tab'] === 'movement'
                ? 'Fast: keluar pada >= 50% hari periode; slow: > 0 dan < 50%; non-moving: tidak ada mutasi keluar. Seluruh jenis mutasi keluar, bukan penjualan saja.'
                : 'Stok awal: sebelum tanggal awal. Qty in/out: selama periode termasuk tanggal akhir.');
            $categoryId = $this->filters['category_id'] ?? '';
            $category = $categoryId === '' ? 'Semua' : ((int) $categoryId === 0 ? 'Tanpa kategori' : (Category::find($categoryId)?->name ?? $categoryId));
            $sheet->mergeCells('A5:'.$lastColumn.'5');
            $sheet->setCellValueExplicit('A5', 'Kategori: '.$category.' | Pencarian: '.($this->filters['q'] ?: 'Semua').' | Status: '.($this->filters['status'] ?: 'Semua').' | Pergerakan: '.($this->filters['tab'] === 'movement' ? (StockPeriodReport::MOVEMENTS[$this->filters['movement'] ?? ''] ?? 'Semua') : 'Semua'), DataType::TYPE_STRING);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A6:'.$lastColumn.'6')->getFont()->setBold(true);
            $sheet->freezePane('F7');
            $sheet->setAutoFilter('A6:'.$lastColumn.max(6, $sheet->getHighestRow()));
        }];
    }
}
