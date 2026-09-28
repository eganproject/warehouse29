<?php

namespace Tests\Feature;

use App\Exports\ItemUpdateTemplateExport;
use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemStock;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ItemSelectiveBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_updates_selected_fields_and_never_changes_sku(): void
    {
        $this->actingAs(User::factory()->create());
        UnitOfMeasure::create(['code' => 'pcs', 'name' => 'Pieces']);
        $item = Item::create([
            'sku' => 'SKU-001',
            'name' => 'Nama Lama',
            'uom' => 'pcs',
            'category_id' => 0,
            'address' => 'Rak A',
            'description' => 'Deskripsi tetap',
            'safety_stock' => 4,
            'is_bundle' => false,
            'is_active' => true,
        ]);

        $response = $this->postJson(route('admin.masterdata.items.bulk-update'), [
            'fields' => ['name', 'address'],
            'file' => $this->xlsx(
                ['sku', 'name', 'address'],
                [['SKU-001', 'Nama Baru', 'Rak B']]
            ),
        ]);

        $response->assertOk()
            ->assertJsonPath('matched', 1)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('unchanged', 0);

        $item->refresh();
        $this->assertSame('SKU-001', $item->sku);
        $this->assertSame('Nama Baru', $item->name);
        $this->assertSame('Rak B', $item->address);
        $this->assertSame('Deskripsi tetap', $item->description);
        $this->assertSame(4, $item->safety_stock);
    }

    public function test_one_invalid_row_rolls_back_the_entire_file(): void
    {
        $this->actingAs(User::factory()->create());
        $first = Item::create([
            'sku' => 'SKU-001',
            'name' => 'Nama Pertama',
            'uom' => 'pcs',
            'category_id' => 0,
            'is_bundle' => false,
            'is_active' => true,
        ]);

        $response = $this->postJson(route('admin.masterdata.items.bulk-update'), [
            'fields' => ['name'],
            'file' => $this->xlsx(
                ['sku', 'name'],
                [
                    ['SKU-001', 'Seharusnya Dibatalkan'],
                    ['SKU-TIDAK-ADA', 'Tidak Valid'],
                ]
            ),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
        $this->assertSame('Nama Pertama', $first->fresh()->name);
    }

    public function test_header_must_match_the_selected_fields(): void
    {
        $this->actingAs(User::factory()->create());
        Item::create([
            'sku' => 'SKU-001',
            'name' => 'Nama Pertama',
            'uom' => 'pcs',
            'category_id' => 0,
            'is_bundle' => false,
            'is_active' => true,
        ]);

        $response = $this->postJson(route('admin.masterdata.items.bulk-update'), [
            'fields' => ['name'],
            'file' => $this->xlsx(
                ['sku', 'name', 'description'],
                [['SKU-001', 'Nama Baru', 'Kolom tidak dipilih']]
            ),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
        $this->assertSame('Nama Pertama', Item::where('sku', 'SKU-001')->value('name'));
    }

    public function test_dynamic_template_contains_sku_and_only_the_selected_field_columns(): void
    {
        Item::create([
            'sku' => '000123',
            'name' => 'Contoh',
            'uom' => 'pcs',
            'category_id' => 0,
            'safety_stock' => 8,
            'is_bundle' => false,
            'is_active' => true,
        ]);

        $export = new ItemUpdateTemplateExport(['name', 'safety_stock']);

        $this->assertSame(['sku', 'name', 'safety_stock'], $export->headings());
        $this->assertSame(
            [['000123', 'Contoh', 8]],
            $export->collection()->values()->all()
        );
    }

    public function test_template_endpoint_generates_a_real_xlsx_file(): void
    {
        $this->actingAs(User::factory()->create());
        Item::create([
            'sku' => 'SKU-001',
            'name' => 'Contoh',
            'uom' => 'pcs',
            'category_id' => 0,
            'is_bundle' => false,
            'is_active' => true,
        ]);

        $response = $this->post(route('admin.masterdata.items.update-template'), [
            'fields' => ['name', 'is_active'],
        ]);

        $response->assertOk()->assertDownload();
        $this->assertStringContainsString(
            'template-update-items-',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_bundle_type_and_components_can_be_updated_safely(): void
    {
        $this->actingAs(User::factory()->create());
        $bundle = Item::create([
            'sku' => 'BUNDLE-001',
            'name' => 'Paket',
            'uom' => 'pcs',
            'category_id' => 0,
            'is_bundle' => false,
            'is_active' => true,
        ]);
        $component = Item::create([
            'sku' => 'COMP-001',
            'name' => 'Komponen',
            'uom' => 'pcs',
            'category_id' => 0,
            'is_bundle' => false,
            'is_active' => true,
        ]);
        ItemStock::create(['item_id' => $bundle->id, 'stock' => 0]);

        $response = $this->postJson(route('admin.masterdata.items.bulk-update'), [
            'fields' => ['bundle'],
            'file' => $this->xlsx(
                ['sku', 'is_bundle', 'bundle_components'],
                [['BUNDLE-001', 'Bundle', 'COMP-001:2']]
            ),
        ]);

        $response->assertOk()->assertJsonPath('updated', 1);
        $this->assertTrue($bundle->fresh()->is_bundle);
        $this->assertDatabaseMissing('item_stocks', ['item_id' => $bundle->id]);
        $this->assertDatabaseHas('item_bundles', [
            'bundle_item_id' => $bundle->id,
            'component_item_id' => $component->id,
            'qty' => 2,
        ]);
        $this->assertSame(1, ItemBundle::where('bundle_item_id', $bundle->id)->count());
    }

    private function xlsx(array $headings, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray(array_merge([$headings], $rows));
        $path = tempnam(sys_get_temp_dir(), 'item-update-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'update-items.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
