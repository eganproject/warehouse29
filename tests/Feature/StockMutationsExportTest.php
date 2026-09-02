<?php

namespace Tests\Feature;

use App\Exports\StockMutationsExport;
use App\Models\Item;
use App\Models\StockMutation;
use App\Models\User;
use App\Support\StreamingXlsxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class StockMutationsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_query_applies_search_and_date_filters(): void
    {
        $user = User::factory()->create(['name' => 'Export Operator']);
        $matchingItem = $this->createItem('SKU-EXPORT', 'Matching Item');
        $otherItem = $this->createItem('SKU-OTHER', 'Other Item');

        $matching = $this->createMutation($matchingItem, $user, [
            'source_code' => 'IN-EXPORT-001',
            'occurred_at' => '2026-05-08 10:00:00',
        ]);
        $this->createMutation($matchingItem, $user, [
            'source_code' => 'IN-EXPORT-OLD',
            'occurred_at' => '2026-05-07 10:00:00',
        ]);
        $this->createMutation($otherItem, $user, [
            'source_code' => 'IN-OTHER-001',
            'occurred_at' => '2026-05-08 11:00:00',
        ]);

        $export = new StockMutationsExport('SKU-EXPORT', '2026-05-08', '2026-05-08');
        $rows = $export->query()->get();

        $this->assertSame([$matching->id], $rows->pluck('id')->all());
    }

    public function test_export_mapping_keeps_zero_stock_and_derives_missing_stock_after_once(): void
    {
        $user = User::factory()->create(['name' => 'Export Operator']);
        $item = $this->createItem('000123', 'Zero Stock Item');
        $mutation = $this->createMutation($item, $user, [
            'qty' => 5,
            'stock_before' => 0,
            'stock_after' => null,
            'occurred_at' => '2026-05-08 10:00:00',
        ])->load(['item', 'creator']);

        $row = (new StockMutationsExport())->map($mutation);

        $this->assertSame('000123', $row[3]);
        $this->assertSame(5, $row[10]);
        $this->assertSame(0, $row[11]);
        $this->assertSame(5, $row[12]);
        $this->assertSame(0, $row[13]);
        $this->assertSame(5, $row[14]);
    }

    public function test_generated_workbook_contains_zero_stock_as_a_numeric_cell(): void
    {
        $user = User::factory()->create(['name' => 'Export Operator']);
        $item = $this->createItem('000123', 'Zero Stock Item');
        $this->createMutation($item, $user, [
            'qty' => 5,
            'stock_before' => 0,
            'stock_after' => 5,
            'source_code' => 'IN-ZERO-001',
            'occurred_at' => '2026-05-08 10:00:00',
        ]);
        $this->createMutation($item, $user, [
            'direction' => 'out',
            'qty' => 2,
            'stock_before' => 5,
            'stock_after' => 3,
            'source_type' => 'outbound',
            'source_subtype' => 'sales',
            'source_id' => 2,
            'source_code' => 'OUT-001',
            'occurred_at' => '2026-05-08 11:00:00',
        ]);

        $export = new StockMutationsExport('', '2026-05-08', '2026-05-08');
        $temporaryFile = app(StreamingXlsxWriter::class)->writeWorkbook(
            $export->workbookSheets('Test Operator')
        );

        try {
            $binary = file_get_contents($temporaryFile);
            $this->assertIsString($binary);
            $this->assertStringNotContainsString("PK\x06\x06", $binary, 'XLSX tidak boleh memakai ZIP64 karena ditolak oleh sebagian Microsoft Excel.');
            $this->assertStringNotContainsString("PK\x06\x07", $binary, 'XLSX tidak boleh memakai ZIP64 locator.');

            $spreadsheet = IOFactory::load($temporaryFile);
            $this->assertSame(['Ringkasan', 'Rekap Sumber', 'Detail Mutasi'], $spreadsheet->getSheetNames());

            $summarySheet = $spreadsheet->getSheetByName('Ringkasan');
            $this->assertSame('Laporan Mutasi Stok', (string) $summarySheet->getCell('B2')->getValue());
            $this->assertSame('Test Operator', (string) $summarySheet->getCell('B6')->getValue());
            $this->assertSame(2, $summarySheet->getCell('B7')->getValue());
            $this->assertSame(5, $summarySheet->getCell('B9')->getValue());
            $this->assertSame(2, $summarySheet->getCell('B10')->getValue());
            $this->assertSame(3, $summarySheet->getCell('B11')->getValue());

            $sourceSheet = $spreadsheet->getSheetByName('Rekap Sumber');
            $this->assertSame('INBOUND', (string) $sourceSheet->getCell('A2')->getValue());
            $this->assertSame(1, $sourceSheet->getCell('C2')->getValue());
            $this->assertSame(5, $sourceSheet->getCell('E2')->getValue());
            $this->assertSame('OUTBOUND', (string) $sourceSheet->getCell('A3')->getValue());
            $this->assertSame(2, $sourceSheet->getCell('F3')->getValue());
            $this->assertSame(-2, $sourceSheet->getCell('G3')->getValue());

            $detailSheet = $spreadsheet->getSheetByName('Detail Mutasi');
            $this->assertSame('000123', (string) $detailSheet->getCell('D2')->getValue());
            $this->assertSame(0, $detailSheet->getCell('K2')->getValue());
            $this->assertSame(2, $detailSheet->getCell('L2')->getValue());
            $this->assertSame(-2, $detailSheet->getCell('M2')->getValue());
            $this->assertSame(5, $detailSheet->getCell('N2')->getValue());
            $this->assertSame(3, $detailSheet->getCell('O2')->getValue());
            $this->assertSame(5, $detailSheet->getCell('K3')->getValue());
            $this->assertSame(0, $detailSheet->getCell('N3')->getValue());
            $this->assertSame(5, $detailSheet->getCell('O3')->getValue());
            $this->assertTrue($detailSheet->getStyle('A1')->getFont()->getBold());
            $this->assertSame('FFFFFFFF', $detailSheet->getStyle('A1')->getFont()->getColor()->getARGB());
            $this->assertSame('FF1F4E78', $detailSheet->getStyle('A1')->getFill()->getStartColor()->getARGB());
            $this->assertSame('A2', $detailSheet->getFreezePane());
            $this->assertSame('A1:T3', $detailSheet->getAutoFilter()->getRange());
            $this->assertSame(32.0, $detailSheet->getColumnDimension('E')->getWidth());
        } finally {
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
            }
            @unlink($temporaryFile);
        }
    }

    private function createItem(string $sku, string $name): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => $name,
            'category_id' => 0,
            'uom' => 'PCS',
            'address' => 'A-01',
        ]);
    }

    private function createMutation(Item $item, User $user, array $overrides = []): StockMutation
    {
        return StockMutation::create(array_merge([
            'item_id' => $item->id,
            'direction' => 'in',
            'qty' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
            'source_type' => 'inbound',
            'source_subtype' => 'purchase',
            'source_id' => 1,
            'source_code' => 'IN-DEFAULT',
            'note' => 'Export test',
            'occurred_at' => '2026-05-08 09:00:00',
            'created_by' => $user->id,
        ], $overrides));
    }
}
