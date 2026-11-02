<?php

namespace Tests\Feature;

use App\Exports\StockMovementAnalysisExport;
use App\Models\Category;
use App\Models\Item;
use App\Models\Kurir;
use App\Models\QcScanResi;
use App\Models\Resi;
use App\Models\Toko;
use App\Models\User;
use App\Support\StockPeriodReport;
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

            $this->assertSame(['Ringkasan', 'Detail SKU', 'Tren Harian', 'Keluar per Toko', 'Kategori per Toko'], $spreadsheet->getSheetNames());

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

    public function test_category_per_toko_sheet_breaks_down_toko_outgoing_by_category(): void
    {
        $user = User::factory()->create();
        $kurir = Kurir::create(['name' => 'Kurir Uji']);
        $alpha = Toko::create(['name' => 'Toko Alpha']);
        $beta = Toko::create(['name' => 'Toko Beta']);
        $akrilik = Category::create(['name' => 'Akrilik']);
        $aksesoris = Category::create(['name' => 'Aksesoris']);
        $qc = function (string $code, Toko $toko) use ($user, $kurir) {
            $resi = Resi::create([
                'id_pesanan' => $code, 'no_resi' => $code, 'uploader_id' => $user->id, 'kurir_id' => $kurir->id,
                'toko_id' => $toko->id, 'status' => 'active', 'tanggal_pesanan' => '2026-09-01', 'tanggal_upload' => '2026-09-01',
            ]);

            return QcScanResi::create(['resi_id' => $resi->id, 'status' => 'completed', 'scanned_at' => '2026-09-01 08:00:00', 'scanned_by' => $user->id, 'completed_at' => '2026-09-01 08:05:00'])->id;
        };
        $item = fn (string $sku, int $categoryId) => Item::create(['sku' => $sku, 'name' => $sku, 'is_active' => true, 'is_bundle' => false, 'category_id' => $categoryId]);
        $a1 = $item('AKR-1', $akrilik->id);
        $a2 = $item('AKR-2', $akrilik->id);
        $x1 = $item('AKS-1', $aksesoris->id);
        $n1 = $item('NOCAT-1', 0);

        $this->mutation($a1, 'out', 300, '2026-09-01 09:00:00', 'qc_resi', $qc('A1', $alpha));
        $this->mutation($a2, 'out', 200, '2026-09-01 09:00:00', 'qc_resi', $qc('A2', $alpha));
        $this->mutation($x1, 'out', 500, '2026-09-01 09:00:00', 'qc_resi', $qc('A3', $alpha));
        $this->mutation($a1, 'out', 100, '2026-09-02 09:00:00', 'qc_resi', $qc('B1', $beta));
        $this->mutation($n1, 'out', 7, '2026-09-02 09:00:00');

        $filters = [
            'date_from' => '2026-09-01', 'date_to' => '2026-09-04', 'stock_type' => 'regular', 'tab' => 'movement',
            'category_id' => '', 'q' => '', 'status' => '', 'movement' => '',
        ];

        $rows = app(StockPeriodReport::class)->outgoingPerTokoCategory($filters)
            ->map(fn ($row) => [$row->toko, $row->category, $row->qty_out, $row->toko_total, $row->category_total, (int) $row->sku_count])
            ->all();
        $this->assertSame([
            ['Toko Alpha', 'Akrilik', 500, 1000, 600, 2],
            ['Toko Alpha', 'Aksesoris', 500, 1000, 500, 1],
            ['Toko Beta', 'Akrilik', 100, 100, 600, 1],
            ['Bukan dari resi (manual/lainnya)', 'Tanpa Kategori', 7, 7, 7, 1],
        ], $rows);

        $binary = Excel::raw(new StockMovementAnalysisExport($filters, 'Supervisor Gudang'), ExcelWriter::XLSX);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'stock-movement-export-');
        file_put_contents($temporaryFile, $binary);

        try {
            $sheet = IOFactory::createReader('Xlsx')->load($temporaryFile)->getSheetByName('Kategori per Toko');
            $this->assertSame('Porsi di Toko (%)', $sheet->getCell('E1')->getValue());
            $this->assertSame([['Toko Alpha', 'Akrilik', 500, 1000, 50.0, 600, 83.33, 2]], $sheet->rangeToArray('A2:H2', null, false, false));
            $this->assertSame([['Toko Beta', 'Akrilik', 100, 100, 100.0, 600, 16.67, 1]], $sheet->rangeToArray('A4:H4', null, false, false));
        } finally {
            @unlink($temporaryFile);
        }
    }

    private function mutation(Item $item, string $direction, int $qty, string $occurredAt, string $sourceType = 'test', int $sourceId = 1): void
    {
        DB::table('stock_mutations')->insert([
            'item_id' => $item->id,
            'direction' => $direction,
            'qty' => $qty,
            'occurred_at' => $occurredAt,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }
}
