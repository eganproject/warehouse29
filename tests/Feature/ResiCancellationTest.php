<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Kurir;
use App\Models\PackerScanOut;
use App\Models\PickingList;
use App\Models\QcScanResi;
use App\Models\QcScanResiItem;
use App\Models\QcTransitItem;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\StockMutation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResiCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_before_qc_only_removes_picking_demand(): void
    {
        [$user, $item, $resi] = $this->makeOrder('BEFORE', 2, 10);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.resi-import.cancel'), [
                'id_pesanan' => $resi->id_pesanan,
                'reason' => 'Pesanan dibatalkan pembeli',
            ])
            ->assertOk()
            ->assertJsonPath('stage', 'before_qc')
            ->assertJsonPath('returned_stock_qty', 0);

        $this->assertDatabaseHas('resis', ['id' => $resi->id, 'status' => 'canceled']);
        $this->assertDatabaseMissing('picking_lists', ['sku' => $item->sku]);
        $this->assertSame(10, $item->stock()->value('stock'));
        $this->assertDatabaseHas('resi_cancellations', [
            'resi_id' => $resi->id,
            'stage' => 'before_qc',
            'returned_stock_qty' => 0,
        ]);
    }

    public function test_cancel_after_partial_qc_returns_only_scanned_stock_and_removes_transit(): void
    {
        [$user, $item, $resi] = $this->makeOrder('PARTIAL', 3, 9, 2);
        $qc = $this->makeQc($user, $item, $resi, 3, 1, 'in_progress');
        $this->makeQcMutation($user, $item, $qc, $resi, 1, 10, 9);
        QcTransitItem::create([
            'item_id' => $item->id,
            'transit_date' => now()->toDateString(),
            'qty' => 1,
            'remaining_qty' => 1,
            'last_qc_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.resi-import.cancel'), [
                'id_pesanan' => $resi->id_pesanan,
                'reason' => 'Barang QC dibatalkan',
            ])
            ->assertOk()
            ->assertJsonPath('stage', 'after_partial_qc')
            ->assertJsonPath('returned_stock_qty', 1);

        $this->assertSame(10, $item->stock()->value('stock'));
        $this->assertDatabaseMissing('qc_transit_items', ['item_id' => $item->id]);
        $this->assertDatabaseHas('stock_mutations', [
            'item_id' => $item->id,
            'direction' => 'in',
            'qty' => 1,
            'source_type' => 'resi_cancellation',
            'source_subtype' => 'reverse_qc',
        ]);
    }

    public function test_cancel_after_scan_out_requires_physical_return_confirmation(): void
    {
        [$user, $item, $resi] = $this->makeOrder('SCANNED-OUT', 2, 8, 0);
        $qc = $this->makeQc($user, $item, $resi, 2, 2, 'completed');
        $this->makeQcMutation($user, $item, $qc, $resi, 2, 10, 8);
        QcTransitItem::create([
            'item_id' => $item->id,
            'transit_date' => now()->toDateString(),
            'qty' => 2,
            'remaining_qty' => 0,
            'last_qc_at' => now(),
        ]);
        $kurir = Kurir::create(['name' => 'Test Courier']);
        $scanOut = PackerScanOut::create([
            'resi_id' => $resi->id,
            'kurir_id' => $kurir->id,
            'scan_type' => 'no_resi',
            'scan_code' => $resi->no_resi,
            'scan_date' => now()->toDateString(),
            'scanned_at' => now(),
            'scanned_by' => $user->id,
        ]);

        $payload = [
            'id_pesanan' => $resi->id_pesanan,
            'reason' => 'Paket ditarik kembali',
        ];
        $this->actingAs($user)
            ->postJson(route('admin.inventory.resi-import.cancel'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirm_stock_returned');

        $this->assertSame(8, $item->stock()->value('stock'));

        $this->actingAs($user)
            ->postJson(route('admin.inventory.resi-import.cancel'), [
                ...$payload,
                'confirm_stock_returned' => true,
            ])
            ->assertOk()
            ->assertJsonPath('stage', 'after_scan_out')
            ->assertJsonPath('returned_stock_qty', 2);

        $this->assertSame(10, $item->stock()->value('stock'));
        $this->assertDatabaseMissing('qc_transit_items', ['item_id' => $item->id]);
        $this->assertDatabaseHas('packer_scan_outs', ['id' => $scanOut->id]);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.resi-import.cancel'), [
                ...$payload,
                'confirm_stock_returned' => true,
            ])
            ->assertOk();
        $this->assertSame(10, $item->stock()->value('stock'));
        $this->assertSame(1, StockMutation::where('source_type', 'resi_cancellation')->count());
    }

    public function test_cancel_bundle_restores_component_stock_and_bundle_transit(): void
    {
        $user = $this->makeUser('bundle');
        $component = Item::create(['sku' => 'COMP-001', 'name' => 'Component']);
        ItemStock::create(['item_id' => $component->id, 'stock' => 7]);
        $bundle = Item::create(['sku' => 'BUNDLE-001', 'name' => 'Bundle', 'is_bundle' => true]);
        $resi = $this->makeResi($user, 'BUNDLE', $bundle->sku, 1);
        $qc = $this->makeQc($user, $bundle, $resi, 1, 1, 'completed');
        $this->makeQcMutation($user, $component, $qc, $resi, 3, 10, 7, 'scan_bundle');
        QcTransitItem::create([
            'item_id' => $bundle->id,
            'transit_date' => now()->toDateString(),
            'qty' => 1,
            'remaining_qty' => 1,
            'last_qc_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.resi-import.cancel'), [
                'id_pesanan' => $resi->id_pesanan,
                'reason' => 'Bundle dibatalkan',
            ])
            ->assertOk()
            ->assertJsonPath('stage', 'after_qc')
            ->assertJsonPath('returned_stock_qty', 3);

        $this->assertSame(10, $component->stock()->value('stock'));
        $this->assertDatabaseMissing('qc_transit_items', ['item_id' => $bundle->id]);
    }

    public function test_before_qc_cancel_can_be_voided_and_used_again_without_duplicate_audit_row(): void
    {
        [$user, $item, $resi] = $this->makeOrder('REOPEN', 2, 10);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.resi-import.cancel'), [
                'id_pesanan' => $resi->id_pesanan,
                'reason' => 'Cancel pertama',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.resi-import.uncancel'), [
                'id_pesanan' => $resi->id_pesanan,
            ])
            ->assertOk();

        $this->assertDatabaseHas('resis', ['id' => $resi->id, 'status' => 'active']);
        $this->assertDatabaseHas('picking_lists', [
            'sku' => $item->sku,
            'qty' => 2,
            'remaining_qty' => 2,
        ]);
        $this->assertNotNull($resi->fresh()->cancellation?->voided_at);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.resi-import.cancel'), [
                'id_pesanan' => $resi->id_pesanan,
                'reason' => 'Cancel kedua',
            ])
            ->assertOk();

        $this->assertSame(1, $resi->fresh()->cancellation()->count());
        $this->assertNull($resi->fresh()->cancellation?->voided_at);
        $this->assertSame('Cancel kedua', $resi->fresh()->cancellation?->reason);
    }

    public function test_resi_from_previous_date_cannot_be_canceled(): void
    {
        [$user, $item, $resi] = $this->makeOrder('PREVIOUS-DATE', 2, 10);
        $previousDate = now()->subDay()->toDateString();
        $resi->tanggal_upload = $previousDate;
        $resi->save();
        PickingList::where('sku', $item->sku)->update(['list_date' => $previousDate]);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.resi-import.cancel'), [
                'id_pesanan' => $resi->id_pesanan,
                'reason' => 'Mencoba cancel data kemarin',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resi');

        $this->assertDatabaseHas('resis', ['id' => $resi->id, 'status' => 'active']);
        $this->assertDatabaseMissing('resi_cancellations', ['resi_id' => $resi->id]);
        $this->assertSame(10, $item->stock()->value('stock'));

        $this->actingAs($user)
            ->getJson(route('admin.inventory.resi-import.data', ['date' => $previousDate]))
            ->assertOk()
            ->assertJsonPath('data.0.can_cancel_today', false);
    }

    private function makeOrder(
        string $suffix,
        int $qty,
        int $stock,
        ?int $remainingPicking = null
    ): array {
        $user = $this->makeUser(strtolower($suffix));
        $item = Item::create([
            'sku' => "SKU-{$suffix}",
            'name' => "Item {$suffix}",
        ]);
        ItemStock::create(['item_id' => $item->id, 'stock' => $stock]);
        $resi = $this->makeResi($user, $suffix, $item->sku, $qty, $remainingPicking);

        return [$user, $item, $resi];
    }

    private function makeUser(string $suffix): User
    {
        return User::create([
            'name' => "User {$suffix}",
            'email' => "{$suffix}@example.test",
            'password' => 'password',
        ]);
    }

    private function makeResi(
        User $user,
        string $suffix,
        string $sku,
        int $qty,
        ?int $remainingPicking = null
    ): Resi {
        $resi = Resi::create([
            'id_pesanan' => "ORDER-{$suffix}",
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => "TRACK-{$suffix}",
            'uploader_id' => $user->id,
        ]);
        ResiDetail::create(['resi_id' => $resi->id, 'sku' => $sku, 'qty' => $qty]);
        PickingList::create([
            'list_date' => now()->toDateString(),
            'sku' => $sku,
            'qty' => $qty,
            'remaining_qty' => $remainingPicking ?? $qty,
        ]);

        return $resi;
    }

    private function makeQc(
        User $user,
        Item $item,
        Resi $resi,
        int $required,
        int $scanned,
        string $status
    ): QcScanResi {
        $qc = QcScanResi::create([
            'resi_id' => $resi->id,
            'status' => $status,
            'scanned_at' => now(),
            'scanned_by' => $user->id,
            'completed_at' => $status === 'completed' ? now() : null,
            'completed_by' => $status === 'completed' ? $user->id : null,
        ]);
        QcScanResiItem::create([
            'qc_scan_resi_id' => $qc->id,
            'item_id' => $item->id,
            'sku' => $item->sku,
            'required_qty' => $required,
            'scanned_qty' => $scanned,
        ]);

        return $qc;
    }

    private function makeQcMutation(
        User $user,
        Item $item,
        QcScanResi $qc,
        Resi $resi,
        int $qty,
        int $before,
        int $after,
        string $subtype = 'scan'
    ): void {
        StockMutation::create([
            'item_id' => $item->id,
            'direction' => 'out',
            'qty' => $qty,
            'stock_before' => $before,
            'stock_after' => $after,
            'source_type' => 'qc_resi',
            'source_subtype' => $subtype,
            'source_id' => $qc->id,
            'source_code' => $resi->no_resi,
            'note' => 'QC scan resi',
            'occurred_at' => now(),
            'created_by' => $user->id,
        ]);
    }
}
