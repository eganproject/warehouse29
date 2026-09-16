<?php

namespace App\Exports;

use App\Support\QcScanPerformanceReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QcScanPerformanceDataSheet implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private string $type,
        private array $filters,
        private ?int $allowedDivisiId
    ) {}

    public function title(): string
    {
        return match ($this->type) {
            'hourly' => 'Performa Per Jam',
            'detail' => 'Detail Resi',
            default => 'Ringkasan Harian',
        };
    }

    public function headings(): array
    {
        return match ($this->type) {
            'hourly' => [
                'Tanggal', 'Akun QC', 'Jam', 'Total Resi', 'Selesai', 'Belum Selesai',
                'Penyelesaian (%)', 'Baris SKU', 'Qty Discan', 'Qty Wajib', 'Pemenuhan Qty (%)',
                'Rata-rata Durasi (menit)',
            ],
            'detail' => [
                'Tanggal', 'Akun QC', 'No. Resi', 'ID Pesanan', 'Status', 'Waktu Scan',
                'Waktu Selesai', 'Durasi (menit)', 'Baris SKU', 'Qty Discan', 'Qty Wajib', 'Pemenuhan Qty (%)',
            ],
            default => [
                'Tanggal', 'Akun QC', 'Total Resi', 'Selesai', 'Belum Selesai', 'Penyelesaian (%)',
                'Jam Aktif', 'Resi/Jam Aktif', 'Rata-rata Durasi (menit)', 'Baris SKU',
                'Qty Discan', 'Qty Wajib', 'Pemenuhan Qty (%)', 'Scan Pertama', 'Scan Terakhir',
            ],
        };
    }

    public function collection(): Collection
    {
        return match ($this->type) {
            'hourly' => $this->hourlyRows(),
            'detail' => $this->detailRows(),
            default => $this->dailyRows(),
        };
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ],
        ];
    }

    public function columnWidths(): array
    {
        if ($this->type === 'detail') {
            return ['A' => 14, 'B' => 24, 'C' => 24, 'D' => 22, 'E' => 16, 'F' => 19, 'G' => 19, 'H' => 16, 'I' => 12, 'J' => 13, 'K' => 13, 'L' => 19];
        }
        if ($this->type === 'hourly') {
            return ['A' => 14, 'B' => 24, 'C' => 18, 'D' => 13, 'E' => 12, 'F' => 15, 'G' => 18, 'H' => 12, 'I' => 13, 'J' => 13, 'K' => 19, 'L' => 24];
        }

        return ['A' => 14, 'B' => 24, 'C' => 13, 'D' => 12, 'E' => 15, 'F' => 18, 'G' => 12, 'H' => 17, 'I' => 24, 'J' => 12, 'K' => 13, 'L' => 13, 'M' => 19, 'N' => 16, 'O' => 16];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(1, $sheet->getHighestRow());
                $lastColumn = $sheet->getHighestColumn();
                $range = "A1:{$lastColumn}{$lastRow}";

                $sheet->freezePane('A2');
                $sheet->setAutoFilter($range);
                $sheet->getRowDimension(1)->setRowHeight(32);
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D8E0EA']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                if ($lastRow >= 2) {
                    for ($row = 2; $row <= $lastRow; $row++) {
                        if ($row % 2 === 0) {
                            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
                        }
                    }
                }
            },
        ];
    }

    private function dailyRows(): Collection
    {
        return QcScanPerformanceReport::dailyQuery($this->filters, $this->allowedDivisiId)
            ->get()
            ->map(function ($row) {
                $total = (int) $row->total_resi;
                $activeHours = (int) $row->active_hours;
                $timed = (int) $row->timed_completed_resi;
                $required = (int) $row->required_qty;

                return [
                    $row->report_date,
                    $row->petugas,
                    $total,
                    (int) $row->completed_resi,
                    (int) $row->pending_resi,
                    $total > 0 ? round((int) $row->completed_resi / $total * 100, 2) : 0,
                    $activeHours,
                    $activeHours > 0 ? round($total / $activeHours, 2) : 0,
                    $timed > 0 ? round((int) $row->completion_seconds_total / $timed / 60, 2) : null,
                    (int) $row->sku_lines,
                    (int) $row->scanned_qty,
                    $required,
                    $required > 0 ? round((int) $row->scanned_qty / $required * 100, 2) : 0,
                    $row->first_scan_at ? Carbon::parse($row->first_scan_at)->format('H:i') : '-',
                    $row->last_scan_at ? Carbon::parse($row->last_scan_at)->format('H:i') : '-',
                ];
            });
    }

    private function hourlyRows(): Collection
    {
        return QcScanPerformanceReport::hourlyQuery($this->filters, $this->allowedDivisiId)
            ->get()
            ->map(function ($row) {
                $total = (int) $row->total_resi;
                $timed = (int) $row->timed_completed_resi;
                $required = (int) $row->required_qty;
                $hour = (int) $row->hour_no;

                return [
                    $row->report_date,
                    $row->petugas,
                    sprintf('%02d:00 - %02d:59', $hour, $hour),
                    $total,
                    (int) $row->completed_resi,
                    (int) $row->pending_resi,
                    $total > 0 ? round((int) $row->completed_resi / $total * 100, 2) : 0,
                    (int) $row->sku_lines,
                    (int) $row->scanned_qty,
                    $required,
                    $required > 0 ? round((int) $row->scanned_qty / $required * 100, 2) : 0,
                    $timed > 0 ? round((int) $row->completion_seconds_total / $timed / 60, 2) : null,
                ];
            });
    }

    private function detailRows(): Collection
    {
        return QcScanPerformanceReport::detailQuery($this->filters, $this->allowedDivisiId)
            ->get()
            ->map(function ($row) {
                $required = (int) $row->required_qty;

                return [
                    $row->report_date,
                    $row->petugas,
                    $row->no_resi ?? '-',
                    $row->id_pesanan ?? '-',
                    $row->status === 'completed' ? 'Selesai' : 'Belum Selesai',
                    $row->scanned_at ? Carbon::parse($row->scanned_at)->format('d-m-Y H:i:s') : '-',
                    $row->completed_at ? Carbon::parse($row->completed_at)->format('d-m-Y H:i:s') : '-',
                    $row->completion_minutes !== null ? (float) $row->completion_minutes : null,
                    (int) $row->sku_lines,
                    (int) $row->scanned_qty,
                    $required,
                    $required > 0 ? round((int) $row->scanned_qty / $required * 100, 2) : 0,
                ];
            });
    }
}
