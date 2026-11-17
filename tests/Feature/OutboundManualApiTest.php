<?php

namespace Tests\Feature;

use App\Models\ApiIpAllowlist;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OutboundTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OutboundManualApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('stock_api.enabled', true);
        config()->set('stock_api.outbound_manual_enabled', true);
        config()->set('stock_api.token', 'test-stock-token');
        config()->set('stock_api.rate_limit_per_minute', 60);
        RateLimiter::clear('stock-api:'.hash('sha256', 'test-stock-token|127.0.0.1'));
        ApiIpAllowlist::create(['ip_address' => '127.0.0.1', 'name' => 'Test runner', 'is_active' => true]);
    }

    public function test_creates_pending_manual_outbound_by_sku(): void
    {
        $a = $this->item('SKU-A');
        $b = $this->item('SKU-B');

        $this->send([
            'ref_no' => 'PO-123',
            'transacted_at' => '2026-11-17T14:00:00+07:00',
            'note' => 'Kirim ke cabang',
            'items' => [
                ['sku' => 'sku-a', 'qty' => 5, 'note' => 'dus 1'],
                ['sku' => 'SKU-B', 'qty' => 2, 'stock_source' => 'damaged'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ref_no', 'PO-123')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.transacted_at', '2026-11-17T14:00:00+07:00')
            ->assertJsonPath('data.note', '[API] Kirim ke cabang')
            ->assertJsonPath('data.total_qty', 7)
            ->assertJsonPath('data.items.0.sku', 'SKU-A')
            ->assertJsonPath('data.items.0.stock_source', 'regular')
            ->assertJsonPath('data.items.1.stock_source', 'damaged');

        $tx = OutboundTransaction::with('items')->sole();
        $this->assertSame('manual', $tx->type);
        $this->assertSame('pending', $tx->status);
        $this->assertStringStartsWith('OUT-MNL-', $tx->code);
        $this->assertNull($tx->created_by);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $tx->items->pluck('item_id')->all());
        $this->assertDatabaseCount('stock_mutations', 0);

        $this->getJson('/api/v1/outbound-manuals/'.$tx->code, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.code', $tx->code);
        $this->getJson('/api/v1/outbound-manuals/PO-123', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.code', $tx->code);
        $this->getJson('/api/v1/outbound-manuals/NOPE', $this->headers())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_api_outbound_deducts_stock_after_admin_approval(): void
    {
        $item = $this->item('SKU-A');
        ItemStock::create(['item_id' => $item->id, 'stock' => 10]);

        $code = $this->send(['ref_no' => 'PO-9', 'items' => [['sku' => 'SKU-A', 'qty' => 4]]])
            ->assertCreated()->json('data.code');
        $this->assertSame(10, (int) ItemStock::where('item_id', $item->id)->value('stock'));

        $user = User::create(['name' => 'Admin', 'email' => 'admin-api-outbound@example.test', 'password' => 'password']);
        $tx = OutboundTransaction::where('code', $code)->firstOrFail();
        $this->actingAs($user)
            ->postJson(route('admin.outbound.manuals.approve', $tx->id))
            ->assertOk();

        $this->assertSame(6, (int) ItemStock::where('item_id', $item->id)->value('stock'));
        $this->getJson('/api/v1/outbound-manuals/'.$code, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_duplicate_ref_no_returns_conflict_with_existing_data(): void
    {
        $this->item('SKU-A');
        $payload = ['ref_no' => 'PO-1', 'items' => [['sku' => 'SKU-A', 'qty' => 1]]];

        $code = $this->send($payload)->assertCreated()->json('data.code');
        $this->send($payload)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DUPLICATE_REF_NO')
            ->assertJsonPath('data.code', $code);

        $this->assertSame(1, OutboundTransaction::count());
    }

    public function test_validation_errors(): void
    {
        $this->item('SKU-A');
        Item::create(['sku' => 'OFF', 'name' => 'Off', 'is_active' => false, 'is_bundle' => false, 'category_id' => 0]);
        Item::create(['sku' => 'BDL', 'name' => 'Bundle', 'is_active' => true, 'is_bundle' => true, 'category_id' => 0]);

        $this->send(['items' => []])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['fields' => ['ref_no', 'items']]]);

        $this->send([
            'ref_no' => 'X',
            'items' => [
                ['sku' => 'SKU-A', 'qty' => 1],
                ['sku' => 'sku-a', 'qty' => 2],
                ['sku' => 'MISSING', 'qty' => 1],
                ['sku' => 'OFF', 'qty' => 1],
                ['sku' => 'BDL', 'qty' => 1, 'stock_source' => 'damaged'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['items.1.sku', 'items.2.sku', 'items.3.sku', 'items.4.sku']]]);

        $this->send(['ref_no' => 'X', 'items' => [['sku' => 'SKU-A', 'qty' => 0]]])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['items.0.qty']]]);

        $this->send(['ref_no' => 'X', 'transacted_at' => '17-11-2026', 'items' => [['sku' => 'SKU-A', 'qty' => 1]]])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['transacted_at']]]);

        $this->assertSame(0, OutboundTransaction::count());
    }

    public function test_requires_token_and_can_be_disabled(): void
    {
        $this->postJson('/api/v1/outbound-manuals', [], ['Accept' => 'application/json'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_TOKEN');

        config()->set('stock_api.outbound_manual_enabled', false);
        $this->send(['ref_no' => 'X', 'items' => [['sku' => 'A', 'qty' => 1]]])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    private function send(array $payload): TestResponse
    {
        return $this->postJson('/api/v1/outbound-manuals', $payload, $this->headers());
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer test-stock-token', 'Accept' => 'application/json'];
    }

    private function item(string $sku): Item
    {
        return Item::create(['sku' => $sku, 'name' => 'Barang '.$sku, 'is_active' => true, 'is_bundle' => false, 'category_id' => 0]);
    }
}
