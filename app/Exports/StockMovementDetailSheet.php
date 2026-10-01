<?php

namespace App\Exports;

use App\Support\StockPeriodReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StockMovementDetailSheet implements FromCollection, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithStrictNullComparison, WithTitle
{
    public function __construct(private Collection $rows) {}

    public function title(): string
    {
        return 'Detail SKU';
    }

    public function headings(): array
    {
        return [
            'Peringkat', 'SKU', 'Nama Barang', 'Kategori', 'Alamat', 'Satuan',
            'Klasifikasi', 'Stok Awal', 'Qty Masuk', 'Qty Keluar', 'Net Movement',
            'Rata-rata Keluar / Hari', 'Kontribusi Qty Keluar (%)', 'Kontribusi Kumulatif (%)',
            'Frekuensi Keluar', 'Hari Aktif Keluar', 'Stok Akhir', 'Safety Stock',
            'Days Cover', 'Terakhir Keluar', 'Status Analisis', 'Saran Tindakan',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows->values()->map(function ($row, int $index) {
            [$status, $action] = $this->analysis($row);

            return [
                $index + 1, $this->safeText($row->sku), $this->safeText($row->name),
                $this->safeText($row->category), $this->safeText($row->address, '-'), $this->safeText($row->uom, '-'),
                StockPeriodReport::MOVEMENTS[$row->movement] ?? $row->movement,
                (int) $row->opening, (int) $row->qty_in, (int) $row->qty_out,
                (int) $row->qty_in - (int) $row->qty_out, round((float) $row->average_out, 2),
                (float) $row->contribution_percent / 100, (float) $row->cumulative_percent / 100,
                (int) $row->outgoing_frequency, (int) $row->outgoing_days, (int) $row->closing,
                (int) $row->safety_stock, $row->days_cover === null ? null : round((float) $row->days_cover, 1),
                $row->last_out_at ? Carbon::parse($row->last_out_at)->format('d/m/Y H:i') : '-',
                $status, $action,
            ];
        });
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 20, 'C' => 34, 'D' => 24, 'E' => 18, 'F' => 12,
            'G' => 18, 'H' => 14, 'I' => 14, 'J' => 14, 'K' => 16, 'L' => 23,
            'M' => 24, 'N' => 25, 'O' => 18, 'P' => 18, 'Q' => 14, 'R' => 15,
            'S' => 15, 'T' => 20, 'U' => 24, 'V' => 54,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '#,##0', 'H' => '#,##0', 'I' => '#,##0', 'J' => '#,##0', 'K' => '#,##0',
            'L' => '#,##0.00', 'M' => '0.00%', 'N' => '0.00%', 'O' => '#,##0', 'P' => '#,##0',
            'Q' => '#,##0', 'R' => '#,##0', 'S' => '#,##0.0',
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $highestRow = max(1, $sheet->getHighestRow());
            $range = "A1:V{$highestRow}";
            $sheet->getStyle('A1:V1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(42);
            $sheet->getStyle($range)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2F3']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            ]);
            $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H2:S{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            for ($row = 2; $row <= $highestRow; $row++) {
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:V{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7FAFC');
                }
                $status = (string) $sheet->getCell("U{$row}")->getValue();
                $color = match ($status) {
                    'Stok habis dengan demand', 'Cakupan kritis' => ['FFC7CE', '9C0006'],
                    'Di bawah safety stock', 'Cakupan perlu dipantau' => ['FFF2CC', '7F6000'],
                    'Non-moving / potensi dead stock' => ['E7E6E6', '595959'],
                    default => ['E2F0D9', '375623'],
                };
                $sheet->getStyle("U{$row}:V{$row}")->applyFromArray([
                    'font' => ['color' => ['rgb' => $color[1]]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color[0]]],
                ]);
            }
            $sheet->freezePane('G2');
            $sheet->setAutoFilter($range);
            $sheet->setShowGridlines(false);
            $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        }];
    }

    private function analysis(object $row): array
    {
        $closing = (float) $row->closing;
        $average = (float) $row->average_out;
        $safety = (float) $row->safety_stock;
        $cover = $row->days_cover === null ? null : (float) $row->days_cover;

        if ((float) $row->qty_out <= 0) {
            return $closing > 0
                ? ['Non-moving / potensi dead stock', 'Evaluasi usia stok; pertimbangkan promosi, transfer, retur, atau hentikan pengadaan.']
                : ['Tidak ada pergerakan', 'Tidak ada demand pada periode; validasi apakah SKU masih perlu dipertahankan.'];
        }
        if ($closing <= 0) {
            return ['Stok habis dengan demand', 'Prioritaskan pengadaan; validasi lead time dan kebutuhan aktual.'];
        }
        if ($safety > 0 && $closing < $safety) {
            return ['Di bawah safety stock', 'Isi ulang hingga melewati safety stock dan tinjau reorder point.'];
        }
        if ($average > 0 && $cover !== null && $cover < 7) {
            return ['Cakupan kritis', 'Segera rencanakan pengadaan; stok diperkirakan kurang dari 7 hari.'];
        }
        if ($average > 0 && $cover !== null && $cover < 30) {
            return ['Cakupan perlu dipantau', 'Pantau lead time dan siapkan pengadaan sebelum cakupan habis.'];
        }

        return ['Stok terkendali', 'Pertahankan pemantauan sesuai pola keluar dan lead time pemasok.'];
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
