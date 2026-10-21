<?php

namespace Tests\Feature;

use App\Models\ApiIpAllowlist;
use App\Models\Item;
use App\Models\Kurir;
use App\Models\QcScanResi;
use App\Models\Resi;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class StockMovementApiTest extends TestCase
{
    use RefreshDatabase;

    private Toko $alpha;

    private Toko $beta;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('stock_api.enabled', true);
        config()->set('stock_api.warehouse_code', 'WSEHA29');
        config()->set('stock_api.token', 'test-stock-token');
        config()->set('stock_api.rate_limit_per_minute', 60);
        RateLimiter::clear('stock-api:'.hash('sha256', 'test-stock-token|127.0.0.1'));
        ApiIpAllowlist::create(['ip_address' => '127.0.0.1', 'name' => 'Test runner', 'is_active' => true]);

        $this->alpha = Toko::create(['name' => 'Toko Alpha']);
        $this->beta = Toko::create(['name' => 'Toko Beta']);
    }

    public function test_returns_movement_rows_summary_and_pagination(): void
    {
        $fast = $this->item('FAST');
        $slow = $this->item('SLOW');
        $this->item('NON');
        $this->mutation($fast, 'in', 100, '2026-08-31 10:00:00');
        $this->mutation($fast, 'out', 30, '2026-09-01 10:00:00');
        $this->mutation($fast, 'out', 20, '2026-09-02 10:00:00');
        $this->mutation($slow, 'out', 5, '2026-09-03 10:00:00');

        $this->api(['date_from' => '2026-09-01', 'date_to' => '2026-09-04', 'per_page' => 2])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.warehouse_code', 'WSEHA29')
            ->assertJsonPath('meta.filters.days', 4)
            ->assertJsonPath('meta.filters.toko', 'Semua toko')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.total_pages', 2)
            ->assertJsonPath('summary.total_sku', 3)
            ->assertJsonPath('summary.qty_out', 55)
            ->assertJsonPath('summary.movement_count.non', 1)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.sku', 'FAST')
            ->assertJsonPath('data.0.opening', 100)
            ->assertJsonPath('data.0.qty_out', 50)
            ->assertJsonPath('data.0.closing', 50)
            ->assertJsonPath('data.0.outgoing_frequency', 2)
            ->assertJsonPath('data.0.average_out_per_day', 12.5)
            ->assertJsonPath('data.0.movement', 'fast')
            ->assertJsonPath('data.0.movement_label', 'Fast Moving')
            ->assertJsonPath('data.0.last_out_at', '2026-09-02T10:00:00+07:00')
            ->assertJsonMissingPath('daily_trend');

        $this->api(['date_from' => '2026-09-01', 'date_to' => '2026-09-04', 'movement' => 'slow'])
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.sku', 'SLOW');

        $this->api(['date_from' => '2026-09-01', 'date_to' => '2026-09-04', 'sku' => 'FAST', 'include' => 'trend'])
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(4, 'daily_trend')
            ->assertJsonPath('daily_trend.1.date', '2026-09-02')
            ->assertJsonPath('daily_trend.1.qty_out', 20);
    }

    public function test_toko_filter_matches_report_and_per_toko_breakdown(): void
    {
        $user = User::factory()->create();
        $kurir = Kurir::create(['name' => 'Kurir Uji']);
        $qc = function (string $code, Toko $toko) use ($user, $kurir) {
            $resi = Resi::create([
                'id_pesanan' => $code, 'no_resi' => $code, 'uploader_id' => $user->id, 'kurir_id' => $kurir->id,
                'toko_id' => $toko->id, 'status' => 'active', 'tanggal_pesanan' => '2026-09-05', 'tanggal_upload' => '2026-09-05',
            ]);

            return QcScanResi::create(['resi_id' => $resi->id, 'status' => 'completed', 'scanned_at' => '2026-09-05 08:00:00', 'scanned_by' => $user->id, 'completed_at' => '2026-09-05 08:05:00'])->id;
        };
        $item = $this->item('STK-1');
        $this->mutation($item, 'in', 100, '2026-09-04 08:00:00', 'inbound', 999);
        $this->mutation($item, 'out', 3, '2026-09-05 08:05:00', 'qc_resi', $qc('A1', $this->alpha));
        $this->mutation($item, 'out', 2, '2026-09-05 09:05:00', 'qc_resi', $qc('B1', $this->beta));
        $this->mutation($item, 'out', 1, '2026-09-05 10:00:00', 'outbound', 1);

        $this->api(['date_from' => '2026-09-01', 'date_to' => '2026-09-05', 'toko_id' => $this->alpha->id, 'include' => 'trend,per_toko'])
            ->assertOk()
            ->assertJsonPath('meta.filters.toko_id', $this->alpha->id)
            ->assertJsonPath('meta.filters.toko', 'Toko Alpha')
            ->assertJsonPath('data.0.qty_out', 3)
            ->assertJsonPath('data.0.closing', 94)
            ->assertJsonPath('daily_trend.4.qty_out', 3)
            ->assertJsonCount(1, 'per_toko')
            ->assertJsonPath('per_toko.0.toko', 'Toko Alpha');

        $perToko = collect($this->api(['date_from' => '2026-09-01', 'date_to' => '2026-09-05', 'include' => 'per_toko'])
            ->assertOk()
            ->assertJsonPath('data.0.qty_out', 6)
            ->json('per_toko'))->keyBy('source');
        $this->assertEquals(1, $perToko['non_resi']['qty_out']);
        $this->assertNull($perToko['non_resi']['toko_id']);
    }

    public function test_invalid_parameters_and_missing_token_return_contract_errors(): void
    {
        $this->getJson('/api/v1/stock-movements')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_TOKEN');

        foreach ([
            ['date_from' => '01-09-2026'],
            ['date_from' => '2026-09-05', 'date_to' => '2026-09-01'],
            ['date_from' => '2026-01-01', 'date_to' => '2027-06-01'],
            ['movement' => 'super'],
            ['toko_id' => 'abc'],
            ['per_page' => 501],
            ['include' => 'trend,unknown'],
        ] as $params) {
            $this->api($params)
                ->assertStatus(400)
                ->assertJsonPath('success', false)
                ->assertJsonPath('error.code', 'INVALID_PARAMETER');
        }
    }

    private function api(array $params)
    {
        return $this->withHeader('Authorization', 'Bearer test-stock-token')
            ->getJson('/api/v1/stock-movements?'.http_build_query($params));
    }

    private function item(string $sku): Item
    {
        return Item::create(['sku' => $sku, 'name' => 'Barang '.$sku, 'is_active' => true, 'is_bundle' => false, 'category_id' => 0]);
    }

    private function mutation(Item $item, string $direction, int $qty, string $date, string $type = 'test', int $sourceId = 1): void
    {
        DB::table('stock_mutations')->insert(['item_id' => $item->id, 'direction' => $direction, 'qty' => $qty, 'occurred_at' => $date, 'source_type' => $type, 'source_id' => $sourceId]);
    }
}
