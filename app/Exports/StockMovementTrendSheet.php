<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
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

class StockMovementTrendSheet implements FromArray, WithCharts, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithStrictNullComparison, WithTitle
{
    public function __construct(private array $trend) {}

    public function title(): string
    {
        return 'Tren Harian';
    }

    public function headings(): array
    {
        return ['Tanggal', 'Hari', 'Qty Masuk', 'Qty Keluar', 'Net Movement', 'Akumulasi Net', 'Rasio Keluar / Masuk'];
    }

    public function array(): array
    {
        $cumulative = 0;

        return collect($this->trend['dates'] ?? [])->map(function (string $date, int $index) use (&$cumulative) {
            $qtyIn = (int) ($this->trend['qty_in'][$index] ?? 0);
            $qtyOut = (int) ($this->trend['qty_out'][$index] ?? 0);
            $net = $qtyIn - $qtyOut;
            $cumulative += $net;

            return [
                Carbon::parse($date)->format('d/m/Y'),
                $this->dayName(Carbon::parse($date)->dayOfWeek),
                $qtyIn, $qtyOut, $net, $cumulative,
                $qtyIn > 0 ? $qtyOut / $qtyIn : null,
            ];
        })->all();
    }

    public function columnWidths(): array
    {
        return ['A' => 15, 'B' => 14, 'C' => 16, 'D' => 16, 'E' => 18, 'F' => 18, 'G' => 22, 'H' => 3, 'I' => 14, 'J' => 14, 'K' => 14, 'L' => 14, 'M' => 14, 'N' => 14, 'O' => 14, 'P' => 14];
    }

    public function columnFormats(): array
    {
        return ['C' => '#,##0', 'D' => '#,##0', 'E' => '#,##0;[Red]-#,##0', 'F' => '#,##0;[Red]-#,##0', 'G' => '0.00%'];
    }

    public function charts(): Chart
    {
        $count = max(1, count($this->trend['dates'] ?? []));
        $end = $count + 1;
        $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Tren Harian'!\$A\$2:\$A\${$end}", null, $count)];
        $seriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Tren Harian'!\$C\$1", null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Tren Harian'!\$D\$1", null, 1),
        ];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Tren Harian'!\$C\$2:\$C\${$end}", null, $count),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Tren Harian'!\$D\$2:\$D\${$end}", null, $count),
        ];
        $series = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, range(0, count($values) - 1), $seriesLabels, $labels, $values);
        $chart = new Chart('daily_movement', new Title('Tren Qty Masuk dan Keluar per Hari'), new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$series]));
        $chart->setTopLeftPosition('I2');
        $chart->setBottomRightPosition('P22');

        return $chart;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $highestRow = max(1, $sheet->getHighestRow());
            $range = "A1:G{$highestRow}";
            $sheet->getStyle('A1:G1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(34);
            $sheet->getStyle($range)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2F3']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("C2:G{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            for ($row = 2; $row <= $highestRow; $row++) {
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7FAFC');
                }
            }
            $sheet->freezePane('C2');
            $sheet->setAutoFilter($range);
            $sheet->setShowGridlines(false);
            $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        }];
    }

    private function dayName(int $dayOfWeek): string
    {
        return ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$dayOfWeek] ?? '-';
    }
}
