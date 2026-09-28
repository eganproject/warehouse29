<?php

namespace App\Exports;

use App\Models\Item;
use App\Support\ItemBulkUpdateFields;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ItemUpdateTemplateExport extends DefaultValueBinder implements FromCollection, WithColumnWidths, WithCustomValueBinder, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /** @var array<int, string> */
    private array $fields;

    public function __construct(array $fields)
    {
        $this->fields = ItemBulkUpdateFields::normalize($fields);
    }

    public function collection(): Collection
    {
        $relations = [];
        if (in_array('category', $this->fields, true)) {
            $relations[] = 'category.parent';
        }
        if (in_array('bundle', $this->fields, true)) {
            $relations[] = 'bundleComponents.componentItem';
        }

        return Item::query()
            ->with($relations)
            ->orderBy('sku')
            ->get()
            ->map(fn (Item $item) => $this->mapItem($item));
    }

    public function headings(): array
    {
        return ItemBulkUpdateFields::headings($this->fields);
    }

    public function columnWidths(): array
    {
        $widths = [];
        foreach ($this->headings() as $index => $heading) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $widths[$column] = match ($heading) {
                'sku' => 24,
                'name' => 38,
                'parent_category', 'category' => 25,
                'description', 'address', 'bundle_components' => 45,
                default => 20,
            };
        }

        return $widths;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($cell->getColumn() === 'A' || is_string($value)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
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
                $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings()));
                $lastRow = max(1, $sheet->getHighestRow());
                $dataRange = "A1:{$lastColumn}{$lastRow}";

                $sheet->freezePane('B2');
                $sheet->setAutoFilter($dataRange);
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getStyle($dataRange)->applyFromArray([
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

                // SKU tetap terkunci. Hanya kolom yang dipilih pengguna yang bisa diedit.
                $sheet->getStyle("A1:A{$lastRow}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E5E7EB'],
                    ],
                    'font' => ['bold' => true, 'color' => ['rgb' => '374151']],
                ]);
                if ($lastColumn !== 'A' && $lastRow >= 2) {
                    $sheet->getStyle("B2:{$lastColumn}{$lastRow}")
                        ->getProtection()
                        ->setLocked(Protection::PROTECTION_UNPROTECTED);
                }

                $sheet->getComment('A1')->getText()->createTextRun(
                    'SKU adalah kunci pencocokan item dan tidak dapat diubah. Hapus baris item yang tidak ingin diperbarui.'
                );
                $protection = $sheet->getProtection();
                $protection->setPassword('warehouse29-items');
                // false berarti aksi tersebut tetap diizinkan saat sheet diproteksi.
                $protection->setAutoFilter(false);
                $protection->setSort(false);
                $protection->setDeleteRows(false);
                $protection->setSheet(true);
            },
        ];
    }

    public function title(): string
    {
        return 'Update Items';
    }

    private function mapItem(Item $item): array
    {
        $row = [$item->sku];

        foreach ($this->fields as $field) {
            $values = match ($field) {
                'name' => [$item->name],
                'uom' => [$item->uom ?? 'pcs'],
                'category' => [
                    (int) $item->category_id === 0 ? '' : ($item->category?->parent?->name ?? ''),
                    (int) $item->category_id === 0 ? '' : ($item->category?->name ?? ''),
                ],
                'address' => [$item->address ?? ''],
                'description' => [$item->description ?? ''],
                'safety_stock' => [(int) ($item->safety_stock ?? 0)],
                'is_active' => [(bool) $item->is_active ? 'Aktif' : 'Nonaktif'],
                'bundle' => [
                    (bool) $item->is_bundle ? 'Bundle' : 'Biasa',
                    (bool) $item->is_bundle
                        ? $item->bundleComponents
                            ->sortBy(fn ($component) => $component->componentItem?->sku ?? '')
                            ->map(fn ($component) => ($component->componentItem?->sku ?? '').':'.(int) $component->qty)
                            ->filter(fn (string $value) => ! str_starts_with($value, ':'))
                            ->implode(' | ')
                        : '',
                ],
                default => [],
            };

            $row = array_merge($row, $values);
        }

        return $row;
    }
}
