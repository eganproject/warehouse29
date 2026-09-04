<?php

namespace Tests\Feature;

use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\OutboundTransaction;
use App\Models\ReturnReason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReturnReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_return_report_page_has_inbound_and_outbound_tabs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.reports.returns.index'))
            ->assertOk()
            ->assertSee('Laporan Retur')
            ->assertSee('Retur Masuk')
            ->assertSee('Retur Keluar');
    }

    public function test_inbound_report_calculates_quality_difference_and_finalization_metrics(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        $user = User::factory()->create(['name' => 'Petugas Retur']);
        $firstItem = Item::create(['sku' => 'RET-IN-001', 'name' => 'Produk Retur A']);
        $secondItem = Item::create(['sku' => 'RET-IN-002', 'name' => 'Produk Retur B']);
        $reason = ReturnReason::where('code', 'DAMAGED')->firstOrFail();

        $finalized = InboundTransaction::create([
            'code' => 'IN-RET-RPT-001',
            'type' => 'return',
            'ref_no' => 'REF-IN-001',
            'return_resi_no' => 'RESI-RETURN-001',
            'transacted_at' => now()->subDay(),
            'status' => 'finalized',
            'approved_at' => now()->subDay(),
            'finalized_at' => now()->subDay(),
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'finalized_by' => $user->id,
        ]);
        $finalized->items()->create([
            'item_id' => $firstItem->id,
            'qty' => 8,
            'qty_resi' => 10,
            'qty_received' => 8,
            'qty_difference' => 2,
            'qty_good' => 6,
            'qty_damaged' => 2,
            'return_reason_id' => $reason->id,
        ]);

        $approved = InboundTransaction::create([
            'code' => 'IN-RET-RPT-002',
            'type' => 'return',
            'transacted_at' => now(),
            'status' => 'approved',
            'approved_at' => now(),
            'created_by' => $user->id,
            'approved_by' => $user->id,
        ]);
        $approved->items()->create([
            'item_id' => $secondItem->id,
            'qty' => 5,
            'qty_resi' => 5,
            'qty_received' => 5,
            'qty_difference' => 0,
            'qty_good' => 5,
            'qty_damaged' => 0,
            'return_reason_id' => $reason->id,
        ]);

        $response = $this->actingAs($user)->getJson(route('admin.reports.returns.data', [
            'direction' => 'inbound',
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-04',
            'length' => 10,
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.transactions', 2)
            ->assertJsonPath('summary.expected_qty', 15)
            ->assertJsonPath('summary.received_qty', 13)
            ->assertJsonPath('summary.difference_qty', 2)
            ->assertJsonPath('summary.good_qty', 11)
            ->assertJsonPath('summary.damaged_qty', 2)
            ->assertJsonPath('summary.stocked_good_qty', 6)
            ->assertJsonPath('summary.stocked_damaged_qty', 2)
            ->assertJsonPath('summary.waiting_stock_qty', 5)
            ->assertJsonPath('summary.approved', 1)
            ->assertJsonPath('summary.finalized', 1)
            ->assertJsonCount(2, 'data');

        $this->assertNotEmpty($response->json('analytics.daily'));
        $this->assertSame('RET-IN-001', $response->json('analytics.top_skus.0.label'));
    }

    public function test_inbound_reason_filter_limits_item_metrics_and_row_items(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        $user = User::factory()->create();
        $firstItem = Item::create(['sku' => 'REASON-001', 'name' => 'Produk Rusak']);
        $secondItem = Item::create(['sku' => 'REASON-002', 'name' => 'Produk Salah']);
        $damaged = ReturnReason::where('code', 'DAMAGED')->firstOrFail();
        $wrongItem = ReturnReason::where('code', 'WRONG_ITEM')->firstOrFail();
        $transaction = InboundTransaction::create([
            'code' => 'IN-RET-REASON',
            'type' => 'return',
            'transacted_at' => now(),
            'status' => 'approved',
            'created_by' => $user->id,
        ]);
        $transaction->items()->createMany([
            ['item_id' => $firstItem->id, 'qty' => 3, 'qty_resi' => 3, 'qty_received' => 3, 'qty_good' => 0, 'qty_damaged' => 3, 'return_reason_id' => $damaged->id],
            ['item_id' => $secondItem->id, 'qty' => 7, 'qty_resi' => 7, 'qty_received' => 7, 'qty_good' => 7, 'qty_damaged' => 0, 'return_reason_id' => $wrongItem->id],
        ]);

        $this->actingAs($user)->getJson(route('admin.reports.returns.data', [
            'direction' => 'inbound',
            'date_from' => '2026-09-04',
            'date_to' => '2026-09-04',
            'reason_id' => $damaged->id,
        ]))->assertOk()
            ->assertJsonPath('summary.transactions', 1)
            ->assertJsonPath('summary.received_qty', 3)
            ->assertJsonPath('summary.damaged_qty', 3)
            ->assertJsonPath('data.0.item_count', 1)
            ->assertJsonPath('data.0.items.0.sku', 'REASON-001');
    }

    public function test_outbound_report_separates_stock_sources_and_approval_effect(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        $user = User::factory()->create(['name' => 'Admin Gudang']);
        $firstItem = Item::create(['sku' => 'RET-OUT-001', 'name' => 'Produk Keluar A']);
        $secondItem = Item::create(['sku' => 'RET-OUT-002', 'name' => 'Produk Keluar B']);

        $approved = OutboundTransaction::create([
            'code' => 'OUT-RET-RPT-001',
            'type' => 'return',
            'transacted_at' => now()->subDay(),
            'status' => 'approved',
            'approved_at' => now()->subDay(),
            'created_by' => $user->id,
            'approved_by' => $user->id,
        ]);
        $approved->items()->createMany([
            ['item_id' => $firstItem->id, 'stock_source' => 'regular', 'qty' => 4],
            ['item_id' => $secondItem->id, 'stock_source' => 'damaged', 'qty' => 2],
        ]);

        $pending = OutboundTransaction::create([
            'code' => 'OUT-RET-RPT-002',
            'type' => 'return',
            'transacted_at' => now(),
            'status' => 'pending',
            'created_by' => $user->id,
        ]);
        $pending->items()->create([
            'item_id' => $firstItem->id,
            'stock_source' => 'regular',
            'qty' => 3,
        ]);

        $response = $this->actingAs($user)->getJson(route('admin.reports.returns.data', [
            'direction' => 'outbound',
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-04',
            'length' => 10,
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.transactions', 2)
            ->assertJsonPath('summary.total_qty', 9)
            ->assertJsonPath('summary.regular_qty', 7)
            ->assertJsonPath('summary.damaged_qty', 2)
            ->assertJsonPath('summary.issued_qty', 6)
            ->assertJsonPath('summary.waiting_approval_qty', 3)
            ->assertJsonPath('summary.approved', 1)
            ->assertJsonPath('summary.pending', 1)
            ->assertJsonCount(2, 'data');

        $this->assertNotEmpty($response->json('analytics.daily'));
        $this->assertSame(7, $response->json('analytics.top_skus.0.value'));
    }
}
