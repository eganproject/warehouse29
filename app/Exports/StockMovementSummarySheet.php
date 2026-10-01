<?php

namespace App\Exports;

use App\Models\Category;
use App\Support\StockPeriodReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class StockMovementSummarySheet implements FromArray, WithCharts, WithColumnWidths, WithEvents, WithTitle
{
    private const CLASSIFICATIONS = [
        'fast' => ['Fast Moving', 'Prioritaskan ketersediaan dan titik pemesanan ulang.'],
        'medium' => ['Medium Moving', 'Pantau tren dan sesuaikan stok secara berkala.'],
        'slow' => ['Slow Moving', 'Batasi pengadaan dan evaluasi stok berlebih.'],
        'non' => ['Non Moving', 'Evaluasi dead stock, promosi, transfer, atau penghentian.'],
    ];

    public function __construct(
        private Collection $rows,
        private array $filters,
        private string $requestedBy = '-'
    ) {}

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function array(): array
    {
        $totalSku = $this->rows->count();
        $qtyIn = (int) $this->rows->sum('qty_in');
        $qtyOut = (int) $this->rows->sum('qty_out');
        $closing = (int) $this->rows->sum('closing');
        $activeSku = $this->rows->where('qty_out', '>', 0)->count();
        $nonMoving = $this->rows->where('movement', 'non')->count();
        $criticalCover = $this->rows->filter(fn ($row) => (float) $row->average_out > 0 && (float) $row->days_cover < 30)->count();
        $stockoutWithDemand = $this->rows->filter(fn ($row) => (float) $row->qty_out > 0 && (float) $row->closing <= 0)->count();
        $topTenQty = (int) $this->rows->sortByDesc('qty_out')->take(10)->sum('qty_out');

        $data = [
            ['LAPORAN ANALISIS PERGERAKAN STOK'],
            ['Dibuat pada', now()->format('d/m/Y H:i'), 'Oleh', $this->safeText($this->requestedBy, '-')],
            [''],
            ['INFORMASI LAPORAN'],
            ['Periode', Carbon::parse($this->filters['date_from'])->format('d/m/Y').' s/d '.Carbon::parse($this->filters['date_to'])->format('d/m/Y')],
            ['Jenis stok', ($this->filters['stock_type'] ?? 'regular') === 'damaged' ? 'Stok rusak' : 'Stok reguler'],
            ['Filter', $this->filterLabel()],
            ['Metode klasifikasi', 'Kontribusi kumulatif qty keluar: Fast adalah lapisan awal hingga ambang 70%, Medium lapisan berikutnya hingga 90%, Slow sisanya, dan Non Moving tanpa qty keluar.'],
            [''],
            ['INDIKATOR UTAMA', 'NILAI', 'KETERANGAN'],
            ['Total SKU', $totalSku, 'Jumlah SKU fisik aktif sesuai filter'],
            ['Qty masuk', $qtyIn, 'Total unit masuk selama periode'],
            ['Qty keluar', $qtyOut, 'Total unit keluar selama periode'],
            ['Net movement', $qtyIn - $qtyOut, 'Qty masuk dikurangi qty keluar'],
            ['Stok akhir', $closing, 'Saldo berdasarkan mutasi sampai akhir periode'],
            ['SKU aktif keluar', $activeSku, $this->ratio($activeSku, $totalSku), 'SKU dengan qty keluar selama periode'],
            ['SKU non-moving', $nonMoving, $this->ratio($nonMoving, $totalSku), 'SKU tanpa qty keluar selama periode'],
            ['Days cover < 30 hari', $criticalCover, $this->ratio($criticalCover, $totalSku), 'Berpotensi perlu pengadaan lebih cepat'],
            ['Stok habis dengan demand', $stockoutWithDemand, $this->ratio($stockoutWithDemand, $totalSku), 'Stok akhir ≤ 0 namun memiliki qty keluar'],
            ['Konsentrasi Top 10 SKU', $topTenQty, $this->ratio($topTenQty, $qtyOut), 'Porsi qty keluar dari 10 SKU teratas'],
            [''],
            ['DISTRIBUSI KLASIFIKASI', 'JUMLAH SKU', '% SKU', 'QTY KELUAR', '% QTY KELUAR', 'FOKUS ANALISIS'],
        ];

        foreach (self::CLASSIFICATIONS as $key => [$label, $focus]) {
            $classified = $this->rows->where('movement', $key);
            $classifiedQty = (int) $classified->sum('qty_out');
            $data[] = [$label, $classified->count(), $this->ratio($classified->count(), $totalSku), $classifiedQty, $this->ratio($classifiedQty, $qtyOut), $focus];
        }

        $data[] = [''];
        $data[] = ['PRIORITAS TINDAKAN', 'JUMLAH SKU', 'ARAH TINDAKAN'];
        $data[] = ['Segera isi ulang', $stockoutWithDemand, 'Validasi kebutuhan dan lead time; prioritaskan SKU yang masih memiliki demand.'];
        $data[] = ['Pantau cakupan < 30 hari', $criticalCover, 'Tinjau reorder point dengan mempertimbangkan lead time dan safety stock.'];
        $data[] = ['Evaluasi non-moving', $nonMoving, 'Cek usia stok dan putuskan promosi, transfer, retur, atau penghentian pengadaan.'];
        $data[] = ['Catatan', 'Gunakan sheet Detail SKU untuk menindaklanjuti SKU dan sheet Tren Harian untuk melihat pola waktu.'];
        $data[] = [''];
        $data[] = ['Catatan data', 'Total qty merupakan gabungan satuan masing-masing barang. Bundle virtual tidak disertakan agar komponen tidak terhitung ganda.'];

        while (count($data) < 22) {
            $data[] = [''];
        }

        $data[21] = array_pad($data[21], 9, '');
        $data[21][7] = 'SKU';
        $data[21][8] = 'Qty Keluar';
        foreach ($this->rows->sortByDesc('qty_out')->take(10)->values() as $index => $row) {
            $target = 22 + $index;
            $data[$target] = array_pad($data[$target] ?? [], 9, '');
            $data[$target][7] = $this->safeText($row->sku, '-');
            $data[$target][8] = (int) $row->qty_out;
        }

        return $data;
    }

    public function columnWidths(): array
    {
        return ['A' => 31, 'B' => 24, 'C' => 18, 'D' => 18, 'E' => 18, 'F' => 58, 'G' => 3, 'H' => 22, 'I' => 16, 'J' => 14, 'K' => 14, 'L' => 14, 'M' => 14, 'N' => 14];
    }

    public function charts(): array
    {
        $classificationLabels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Ringkasan'!\$A\$23:\$A\$26", null, 4)];
        $classificationValues = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Ringkasan'!\$B\$23:\$B\$26", null, 4)];
        $classificationSeries = new DataSeries(DataSeries::TYPE_PIECHART, null, range(0, count($classificationValues) - 1), [], $classificationLabels, $classificationValues);
        $classificationSeries->setPlotDirection(DataSeries::DIRECTION_COL);
        $distribution = new Chart('classification_distribution', new Title('Distribusi SKU per Klasifikasi'), new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea(null, [$classificationSeries]));
        $distribution->setTopLeftPosition('H2');
        $distribution->setBottomRightPosition('N16');

        $topCount = min(10, $this->rows->count());
        $topEnd = 22 + max(1, $topCount);
        $topLabels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Ringkasan'!\$H\$23:\$H\${$topEnd}", null, max(1, $topCount))];
        $topValues = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Ringkasan'!\$I\$23:\$I\${$topEnd}", null, max(1, $topCount))];
        $topSeries = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, range(0, count($topValues) - 1), [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Ringkasan'!\$I\$22", null, 1)], $topLabels, $topValues);
        $topSeries->setPlotDirection(DataSeries::DIRECTION_BAR);
        $topChart = new Chart('top_sku', new Title('Top 10 SKU berdasarkan Qty Keluar'), null, new PlotArea(null, [$topSeries]));
        $topChart->setTopLeftPosition('H18');
        $topChart->setBottomRightPosition('N34');

        return [$distribution, $topChart];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $sheet->mergeCells('A1:F1');
            $sheet->getRowDimension(1)->setRowHeight(36);
            $sheet->getStyle('A1:F1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            foreach ([4, 10, 22, 28] as $row) {
                $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F75B5']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);
            }

            $sheet->getStyle('A4:F34')->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2F3']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            ]);
            $sheet->getStyle('B11:B20')->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('C16:C20')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet->getStyle('B23:B26')->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('C23:C26')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet->getStyle('D23:D26')->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('E23:E26')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet->getStyle('I23:I32')->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('A11:A20')->getFont()->setBold(true);
            $sheet->getStyle('H22:I22')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F75B5']],
            ]);
            $sheet->getStyle('A23:F23')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2F0D9');
            $sheet->getStyle('A24:F24')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
            $sheet->getStyle('A25:F25')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');
            $sheet->getStyle('A26:F26')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7E6E6');
            if ((int) $sheet->getCell('B19')->getValue() > 0) {
                $sheet->getStyle('A19:D19')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '9C0006']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC7CE']]]);
            }
            $sheet->freezePane('A4');
            $sheet->setShowGridlines(false);
            $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
        }];
    }

    private function filterLabel(): string
    {
        $categoryId = $this->filters['category_id'] ?? '';
        $category = $categoryId === '' ? 'Semua kategori' : ((int) $categoryId === 0 ? 'Tanpa kategori' : (Category::find($categoryId)?->name ?? (string) $categoryId));
        $status = match ($this->filters['status'] ?? '') {
            'positive' => 'stok positif', 'zero' => 'stok nol', 'negative' => 'stok negatif', 'low' => 'di bawah safety stock', default => 'semua status',
        };
        $movement = StockPeriodReport::MOVEMENTS[$this->filters['movement'] ?? ''] ?? 'Semua pergerakan';
        $search = $this->filters['q'] ?? '';

        return $this->safeText("{$category}; {$status}; {$movement}; SKU: ".($search !== '' ? $search : 'semua'));
    }

    private function ratio(int|float $value, int|float $total): float
    {
        return $total > 0 ? $value / $total : 0;
    }

    private function safeText(mixed $value, string $fallback = ''): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return $fallback;
        }

        return $text !== '-' && preg_match('/^[=+\-@]/', $text) ? "'{$text}" : $text;
    }
}
