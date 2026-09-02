<?php

namespace Tests\Feature;

use App\Exports\StockMutationsExport;
use App\Models\Item;
use App\Models\StockMutation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
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

        $this->assertInstanceOf(FromQuery::class, $export);
        $this->assertInstanceOf(WithStrictNullComparison::class, $export);
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

        $this->assertSame('000123', $row[2]);
        $this->assertSame(0, $row[7]);
        $this->assertSame(5, $row[8]);
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

        $contents = Excel::raw(
            new StockMutationsExport('', '2026-05-08', '2026-05-08'),
            ExcelWriter::XLSX
        );
        $temporaryFile = tempnam(storage_path('framework/cache'), 'stock-mutations-export-');
        $this->assertNotFalse($temporaryFile);

        try {
            file_put_contents($temporaryFile, $contents);
            $spreadsheet = IOFactory::load($temporaryFile);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('000123', $sheet->getCell('C2')->getValue());
            $this->assertSame(0, $sheet->getCell('H2')->getValue());
            $this->assertSame(5, $sheet->getCell('I2')->getValue());
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
