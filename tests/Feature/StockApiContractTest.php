<?php

namespace Tests\Feature;

use App\Models\ApiIpAllowlist;
use App\Models\StockApiSyncRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class StockApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('stock_api.enabled', true);
        config()->set('stock_api.warehouse_code', 'WSEHA29');
        config()->set('stock_api.token', 'test-stock-token');
        config()->set('stock_api.rate_limit_per_minute', 60);
        RateLimiter::clear('stock-api:'.hash('sha256', 'test-stock-token|127.0.0.1'));
        ApiIpAllowlist::create(['ip_address' => '127.0.0.1', 'name' => 'Test runner', 'is_active' => true]);
    }

    public function test_stock_endpoint_follows_contract_and_filters_timestamp_with_offset(): void
    {
        StockApiSyncRecord::create([
            'sku' => 'API-002', 'name' => 'Barang Kedua', 'category' => null, 'uom' => 'box',
            'qty' => 2, 'min_qty' => null, 'status' => 'inactive', 'source_updated_at' => '2026-07-23 09:00:00',
        ]);
        StockApiSyncRecord::create([
            'sku' => 'API-001', 'name' => 'Barang Pertama', 'category' => 'ATK', 'uom' => 'pcs',
            'qty' => 12, 'min_qty' => 3, 'status' => 'active', 'source_updated_at' => '2026-07-23 09:00:00',
        ]);

        $this->withHeader('Authorization', 'Bearer test-stock-token')
            ->getJson('/api/v1/stocks?updated_since=2026-07-23T09%3A00%3A00%2B07%3A00&per_page=500')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.warehouse_code', 'WSEHA29')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.sku', 'API-001')
            ->assertJsonPath('data.0.qty', 12)
            ->assertJsonPath('data.1.category', null)
            ->assertJsonPath('data.1.min_qty', null);
    }

    public function test_stock_endpoint_returns_contract_errors_for_invalid_token_and_time(): void
    {
        $this->getJson('/api/v1/stocks')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_TOKEN');

        $this->withHeader('Authorization', 'Bearer test-stock-token')
            ->getJson('/api/v1/stocks?updated_since=2026-07-23 09:00:00')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_PARAMETER');
    }

    public function test_stock_endpoint_enforces_ip_allowlist_and_rate_limit(): void
    {
        ApiIpAllowlist::query()->update(['is_active' => false]);
        $this->withHeader('Authorization', 'Bearer test-stock-token')
            ->getJson('/api/v1/health')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        ApiIpAllowlist::query()->update(['is_active' => true]);
        config()->set('stock_api.rate_limit_per_minute', 1);
        $this->withHeader('Authorization', 'Bearer test-stock-token')->getJson('/api/v1/health')->assertOk();
        $this->withHeader('Authorization', 'Bearer test-stock-token')
            ->getJson('/api/v1/health')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
    }
}
