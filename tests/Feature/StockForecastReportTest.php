<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemStock;
use App\Models\PackerScanException;
use App\Models\PackerScanOut;
use App\Models\Resi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StockForecastReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_forecast_page_explains_inputs_and_formula(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.reports.stock-forecast.index'))
            ->assertOk()
            ->assertSee('Forecast Pengadaan')
            ->assertSee('Rata-rata Penjualan')
            ->assertSee('Target Persediaan')
            ->assertSee('Pengadaan = target stok');
    }

    public function test_forecast_uses_scan_out_sales_and_expands_bundle_demand_to_components(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        $user = User::factory()->create();
        $componentA = Item::create([
            'sku' => 'COMP-A',
            'name' => 'Komponen A',
            'safety_stock' => 2,
            'is_bundle' => false,
            'is_active' => true,
        ]);
        $componentB = Item::create([
            'sku' => 'COMP-B',
            'name' => 'Komponen B',
            'safety_stock' => 0,
            'is_bundle' => false,
            'is_active' => true,
        ]);
        $bundle = Item::create([
            'sku' => 'BUNDLE-AB',
            'name' => 'Bundle AB',
            'is_bundle' => true,
            'is_active' => true,
        ]);
        ItemBundle::create(['bundle_item_id' => $bundle->id, 'component_item_id' => $componentA->id, 'qty' => 2]);
        ItemBundle::create(['bundle_item_id' => $bundle->id, 'component_item_id' => $componentB->id, 'qty' => 1]);
        ItemStock::create(['item_id' => $componentA->id, 'stock' => 5]);
        ItemStock::create(['item_id' => $componentB->id, 'stock' => 10]);

        $this->createScannedResi($user, 'ORDER-FORECAST-1', [
            ['sku' => 'comp-a', 'qty' => 10],
            ['sku' => 'BUNDLE-AB', 'qty' => 2],
        ]);

        $response = $this->actingAs($user)->getJson(route('admin.reports.stock-forecast.data', [
            'history_days' => 7,
            'coverage_days' => 6,
            'q' => 'COMP-A',
            'length' => 10,
        ]));

        $response->assertOk()
            ->assertJsonPath('parameters.history_days', 7)
            ->assertJsonPath('parameters.coverage_days', 6)
            ->assertJsonPath('summary.total_sku', 1)
            ->assertJsonPath('summary.sales_qty', 14)
            ->assertJsonPath('summary.average_daily_sales', 2)
            ->assertJsonPath('summary.current_stock', 5)
            ->assertJsonPath('summary.target_stock', 12)
            ->assertJsonPath('summary.suggested_purchase', 7)
            ->assertJsonPath('data.0.direct_sales_qty', 10)
            ->assertJsonPath('data.0.bundle_demand_qty', 4)
            ->assertJsonPath('data.0.sales_qty', 14)
            ->assertJsonPath('data.0.days_cover', 2.5)
            ->assertJsonPath('data.0.forecast_status', 'reorder')
            ->assertJsonPath('data.0.suggested_purchase', 7);

        $this->assertSame(10, array_sum(array_column($response->json('analytics.daily'), 'direct_qty')));
        $this->assertSame(4, array_sum(array_column($response->json('analytics.daily'), 'bundle_qty')));
    }

    public function test_forecast_excludes_canceled_and_exception_skus(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        $user = User::factory()->create();
        $canceledItem = Item::create(['sku' => 'CANCELED-SKU', 'name' => 'Canceled', 'is_bundle' => false, 'is_active' => true]);
        $exceptionItem = Item::create(['sku' => 'EXCEPTION-SKU', 'name' => 'Exception', 'is_bundle' => false, 'is_active' => true]);
        ItemStock::create(['item_id' => $canceledItem->id, 'stock' => 1]);
        ItemStock::create(['item_id' => $exceptionItem->id, 'stock' => 1]);
        PackerScanException::create(['sku' => 'EXCEPTION-SKU']);

        $this->createScannedResi($user, 'ORDER-CANCELED', [['sku' => 'CANCELED-SKU', 'qty' => 100]], 'canceled');
        $this->createScannedResi($user, 'ORDER-EXCEPTION', [['sku' => 'EXCEPTION-SKU', 'qty' => 50]]);

        $response = $this->actingAs($user)->getJson(route('admin.reports.stock-forecast.data', [
            'history_days' => 30,
            'coverage_days' => 30,
            'length' => 10,
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.sales_qty', 0)
            ->assertJsonPath('summary.suggested_purchase', 0)
            ->assertJsonPath('summary.no_sales_sku', 2);
    }

    public function test_forecast_can_add_safety_stock_to_procurement_target(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        $user = User::factory()->create();
        $item = Item::create([
            'sku' => 'SAFE-SKU',
            'name' => 'Safety Item',
            'safety_stock' => 3,
            'is_bundle' => false,
            'is_active' => true,
        ]);
        ItemStock::create(['item_id' => $item->id, 'stock' => 4]);
        $this->createScannedResi($user, 'ORDER-SAFETY', [['sku' => 'SAFE-SKU', 'qty' => 7]]);

        $this->actingAs($user)->getJson(route('admin.reports.stock-forecast.data', [
            'history_days' => 7,
            'coverage_days' => 7,
            'include_safety_stock' => 1,
        ]))->assertOk()
            ->assertJsonPath('parameters.include_safety_stock', true)
            ->assertJsonPath('data.0.target_stock', 10)
            ->assertJsonPath('data.0.suggested_purchase', 6);
    }

    private function createScannedResi(User $user, string $orderNo, array $details, string $status = 'active'): Resi
    {
        $resi = Resi::create([
            'id_pesanan' => $orderNo,
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-'.$orderNo,
            'status' => $status,
            'uploader_id' => $user->id,
        ]);
        $resi->details()->createMany($details);
        PackerScanOut::create([
            'resi_id' => $resi->id,
            'scan_type' => 'no_resi',
            'scan_code' => $resi->no_resi,
            'scan_date' => now()->toDateString(),
            'scanned_at' => now(),
            'scanned_by' => $user->id,
        ]);

        return $resi;
    }
}
