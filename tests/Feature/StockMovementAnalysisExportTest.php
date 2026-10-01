<?php

namespace Tests\Feature;

use App\Exports\StockMovementAnalysisExport;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class StockMovementAnalysisExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_workbook_contains_analysis_tables_and_charts(): void
    {
        $fast = Item::create([
            'sku' => 'FAST-001',
            'name' => 'Barang Cepat',
            'is_active' => true,
            'is_bundle' => false,
            'category_id' => 0,
            'safety_stock' => 20,
        ]);
        $nonMoving = Item::create([
            'sku' => 'NON-001',
            'name' => 'Barang Tidak Bergerak',
            'is_active' => true,
            'is_bundle' => false,
            'category_id' => 0,
        ]);

        $this->mutation($fast, 'in', 100, '2026-08-31 08:00:00');
        $this->mutation($fast, 'out', 40, '2026-09-01 09:00:00');
        $this->mutation($fast, 'in', 5, '2026-09-02 10:00:00');
        $this->mutation($nonMoving, 'in', 10, '2026-08-31 08:00:00');

        $filters = [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-04',
            'stock_type' => 'regular',
            'tab' => 'movement',
            'category_id' => '',
            'q' => '',
            'status' => '',
            'movement' => '',
        ];
        $binary = Excel::raw(new StockMovementAnalysisExport($filters, 'Supervisor Gudang'), ExcelWriter::XLSX);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'stock-movement-export-');
        file_put_contents($temporaryFile, $binary);

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setIncludeCharts(true);
            $spreadsheet = $reader->load($temporaryFile);

            $this->assertSame(['Ringkasan', 'Detail SKU', 'Tren Harian'], $spreadsheet->getSheetNames());

            $summary = $spreadsheet->getSheetByName('Ringkasan');
            $this->assertSame('LAPORAN ANALISIS PERGERAKAN STOK', $summary->getCell('A1')->getValue());
            $this->assertSame(2, $summary->getCell('B11')->getValue());
            $this->assertSame(5, $summary->getCell('B12')->getValue());
            $this->assertSame(40, $summary->getCell('B13')->getValue());
            $this->assertSame(1, $summary->getCell('B17')->getValue());
            $this->assertSame('Fast Moving', $summary->getCell('A23')->getValue());
            $this->assertSame('SKU', $summary->getCell('H22')->getValue());
            $this->assertSame('FAST-001', $summary->getCell('H23')->getValue());
            $this->assertSame(40, $summary->getCell('I23')->getValue());
            $this->assertCount(2, $summary->getChartCollection());

            $detail = $spreadsheet->getSheetByName('Detail SKU');
            $this->assertSame('FAST-001', $detail->getCell('B2')->getValue());
            $this->assertSame('Fast Moving', $detail->getCell('G2')->getValue());
            $this->assertSame('Cakupan kritis', $detail->getCell('U2')->getValue());
            $this->assertSame('NON-001', $detail->getCell('B3')->getValue());
            $this->assertSame('Non-moving / potensi dead stock', $detail->getCell('U3')->getValue());
            $this->assertSame('G2', $detail->getFreezePane());

            $trend = $spreadsheet->getSheetByName('Tren Harian');
            $this->assertSame('01/09/2026', $trend->getCell('A2')->getValue());
            $this->assertSame(40, $trend->getCell('D2')->getValue());
            $this->assertSame(5, $trend->getCell('C3')->getValue());
            $this->assertCount(1, $trend->getChartCollection());
        } finally {
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
            }
            @unlink($temporaryFile);
        }
    }

    private function mutation(Item $item, string $direction, int $qty, string $occurredAt): void
    {
        DB::table('stock_mutations')->insert([
            'item_id' => $item->id,
            'direction' => $direction,
            'qty' => $qty,
            'occurred_at' => $occurredAt,
            'source_type' => 'test',
            'source_id' => 1,
        ]);
    }
}
