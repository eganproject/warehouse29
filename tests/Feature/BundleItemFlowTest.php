<?php

namespace Tests\Feature;

use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemStock;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\QcScanResi;
use App\Models\QcScanResiBundleComponent;
use App\Models\QcScanResiItem;
use App\Models\QcTransitItem;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\StockMutation;
use App\Models\User;
use App\Support\BundleService;
use App\Support\PickingDemand;
use App\Support\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BundleItemFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Item $trip;
    private Item $bundle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'QC User',
            'email' => 'qc-bundle@example.test',
            'password' => 'password',
        ]);
        // BNDL01 = 10 x TRIP1
        $this->trip = Item::create(['sku' => 'TRIP1', 'name' => 'Trip satuan']);
        ItemStock::create(['item_id' => $this->trip->id, 'stock' => 35]);
        $this->bundle = Item::create(['sku' => 'BNDL01', 'name' => 'Bundle Trip isi 10', 'is_bundle' => true]);
        ItemBundle::create(['bundle_item_id' => $this->bundle->id, 'component_item_id' => $this->trip->id, 'qty' => 10]);
    }

    public function test_virtual_stock_follows_component_stock(): void
    {
        $this->assertSame(3, BundleService::getVirtualStock($this->bundle->id));

        ItemStock::where('item_id', $this->trip->id)->update(['stock' => 9]);
        $this->assertSame(0, BundleService::getVirtualStock($this->bundle->id));
    }

    public function test_qc_rejects_scanning_the_bundle_sku_itself(): void
    {
        $resi = $this->makeResi('A', ['BNDL01' => 1]);

        $this->scan($resi, 'BNDL01', 1)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertSame(35, $this->tripStock());
        $this->assertSame(0, StockMutation::count());
    }

    public function test_qc_counts_component_scans_toward_the_bundle(): void
    {
        $resi = $this->makeResi('B', ['BNDL01' => 2]);

        $this->actingAs($this->user)
            ->getJson(route('qc.resi-lookup', ['type' => 'no_resi', 'code' => $resi->no_resi]))
            ->assertOk()
            ->assertJsonPath('items.0.sku', 'BNDL01')
            ->assertJsonPath('items.0.is_bundle', true)
            ->assertJsonPath('items.0.bundle_label', 'TRIP1 x10')
            ->assertJsonPath('items.0.components.0.sku', 'TRIP1')
            ->assertJsonPath('items.0.components.0.required_qty', 20);

        // 7 dari 10 komponen: stok komponen langsung terpotong, bundle belum lengkap.
        $this->scan($resi, 'TRIP1', 7)
            ->assertOk()
            ->assertJsonPath('scan.target', 'bundle')
            ->assertJsonPath('scan.bundle_completed', 0)
            ->assertJsonPath('items.0.scanned_qty', 0)
            ->assertJsonPath('items.0.components.0.scanned_qty', 7);

        $this->assertSame(28, $this->tripStock());
        $this->assertSame(0, (int) QcTransitItem::where('item_id', $this->bundle->id)->sum('qty'));
        // Picking list berisi barang fisik dan terpotong per scan komponen.
        $this->assertSame(13, (int) PickingList::where('sku', 'TRIP1')->value('remaining_qty'));
        $this->assertNull(PickingList::where('sku', 'BNDL01')->first());

        // Genap 10 -> bundle pertama lengkap; transit dicatat per SKU bundle (untuk scan out).
        $this->scan($resi, 'TRIP1', 3)
            ->assertOk()
            ->assertJsonPath('scan.bundle_completed', 1)
            ->assertJsonPath('items.0.scanned_qty', 1);

        $this->assertSame(1, (int) QcTransitItem::where('item_id', $this->bundle->id)->sum('qty'));
        $this->assertSame(10, (int) PickingList::where('sku', 'TRIP1')->value('remaining_qty'));
        $this->assertSame('in_progress', QcScanResi::where('resi_id', $resi->id)->value('status'));

        $this->scan($resi, 'TRIP1', 10)->assertOk()->assertJsonPath('items.0.scanned_qty', 2);

        $this->assertSame(15, $this->tripStock());
        $this->assertSame('completed', QcScanResi::where('resi_id', $resi->id)->value('status'));
        $this->assertSame(0, (int) PickingList::where('sku', 'TRIP1')->value('remaining_qty'));
        $this->assertSame(0, StockMutation::where('item_id', $this->bundle->id)->count());
        $this->assertSame(3, StockMutation::where('item_id', $this->trip->id)->where('source_subtype', 'scan_bundle')->count());
    }

    public function test_qc_rejects_component_qty_above_what_the_resi_needs(): void
    {
        $resi = $this->makeResi('C', ['BNDL01' => 1]);

        $this->scan($resi, 'TRIP1', 11)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('qty');

        $this->assertSame(35, $this->tripStock());
    }

    public function test_qc_checks_picking_list_on_first_component_scan_not_only_on_completion(): void
    {
        $resi = $this->makeResi('L', ['BNDL01' => 1]);
        DB::table('picking_lists')->where('sku', 'TRIP1')->update(['remaining_qty' => 0]);

        // Ditolak di scan komponen pertama, sebelum stok terpotong.
        $this->scan($resi, 'TRIP1', 1)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('qty');

        $this->assertSame(35, $this->tripStock());
    }

    public function test_qc_fills_direct_sku_line_before_bundle_component(): void
    {
        $resi = $this->makeResi('D', ['TRIP1' => 2, 'BNDL01' => 1]);

        $this->scan($resi, 'TRIP1', 2)->assertOk()->assertJsonPath('scan.target', 'item');
        $this->scan($resi, 'TRIP1', 10)->assertOk()->assertJsonPath('scan.target', 'bundle');

        $this->assertSame(23, $this->tripStock());
        $this->assertSame('completed', QcScanResi::where('resi_id', $resi->id)->value('status'));
        $this->assertSame(2, (int) QcTransitItem::where('item_id', $this->trip->id)->sum('qty'));
        $this->assertSame(1, (int) QcTransitItem::where('item_id', $this->bundle->id)->sum('qty'));
    }

    public function test_qc_component_scan_is_idempotent_per_request_id(): void
    {
        $resi = $this->makeResi('E', ['BNDL01' => 1]);

        $this->scan($resi, 'TRIP1', 4, 'req-1')->assertOk();
        $this->scan($resi, 'TRIP1', 4, 'req-1')->assertOk();

        $this->assertSame(31, $this->tripStock());
        $this->assertSame(4, (int) QcScanResiBundleComponent::sum('scanned_qty'));
    }

    public function test_qc_uses_bundle_composition_snapshot_taken_when_qc_started(): void
    {
        $resi = $this->makeResi('F', ['BNDL01' => 1]);
        $this->scan($resi, 'TRIP1', 1)->assertOk();

        // Komposisi master diubah di tengah QC: resi yang sedang berjalan tetap 10 per bundle.
        ItemBundle::where('bundle_item_id', $this->bundle->id)->update(['qty' => 5]);

        $this->scan($resi, 'TRIP1', 4)->assertOk()->assertJsonPath('items.0.scanned_qty', 0);
        $this->scan($resi, 'TRIP1', 5)->assertOk()->assertJsonPath('items.0.scanned_qty', 1);
        $this->assertSame(25, $this->tripStock());
    }

    public function test_legacy_bundle_qc_progress_is_initialised_from_scanned_bundles(): void
    {
        $resi = $this->makeResi('G', ['BNDL01' => 2]);
        // QC lama: 1 bundle sudah discan (stok komponen sudah dipotong oleh alur lama).
        $qc = QcScanResi::create([
            'resi_id' => $resi->id,
            'status' => 'in_progress',
            'scanned_at' => now(),
            'scanned_by' => $this->user->id,
        ]);
        QcScanResiItem::create([
            'qc_scan_resi_id' => $qc->id,
            'item_id' => $this->bundle->id,
            'sku' => 'BNDL01',
            'required_qty' => 2,
            'scanned_qty' => 1,
        ]);

        $this->scan($resi, 'TRIP1', 10)->assertOk()->assertJsonPath('items.0.scanned_qty', 2);

        $this->assertSame(20, (int) QcScanResiBundleComponent::value('scanned_qty'));
        $this->assertSame('completed', $qc->fresh()->status);
    }

    public function test_bundle_line_with_scanned_components_is_kept_when_resi_details_change(): void
    {
        $resi = $this->makeResi('K', ['BNDL01' => 1]);
        $this->scan($resi, 'TRIP1', 3)->assertOk();

        // Detail resi berubah setelah QC berjalan: baris bundle masih menyimpan jejak stok terpotong.
        ResiDetail::where('resi_id', $resi->id)->update(['sku' => 'TRIP1', 'qty' => 1]);
        $this->actingAs($this->user)
            ->postJson(route('qc.resi-record'), ['resi_id' => $resi->id])
            ->assertOk();

        $this->assertDatabaseHas('qc_scan_resi_items', ['sku' => 'BNDL01']);
        $this->assertSame(3, (int) QcScanResiBundleComponent::sum('scanned_qty'));
    }

    public function test_cancel_after_partial_component_scan_returns_component_stock(): void
    {
        $resi = $this->makeResi('H', ['BNDL01' => 1]);
        $this->scan($resi, 'TRIP1', 6)->assertOk();

        $this->actingAs($this->user)
            ->postJson(route('admin.inventory.resi-import.cancel'), [
                'id_pesanan' => $resi->id_pesanan,
                'reason' => 'Pembeli batal',
            ])
            ->assertOk()
            ->assertJsonPath('stage', 'after_partial_qc')
            ->assertJsonPath('returned_stock_qty', 6);

        $this->assertSame(35, $this->tripStock());
    }

    public function test_stock_service_refuses_physical_mutation_on_bundle(): void
    {
        $this->expectException(ValidationException::class);

        StockService::mutate([
            'item_id' => $this->bundle->id,
            'direction' => 'in',
            'qty' => 1,
            'source_type' => 'test',
            'source_id' => 1,
        ]);
    }

    public function test_inbound_receipt_rejects_bundle_item(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('admin.inbound.receipts.store'), [
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [['item_id' => $this->bundle->id, 'qty' => 5]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(0, InboundTransaction::count());
    }

    public function test_stock_adjustment_rejects_bundle_item(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('admin.inventory.stock-adjustments.store'), [
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [['item_id' => $this->bundle->id, 'direction' => 'in', 'qty' => 5]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_inbound_return_lookup_converts_bundle_to_components(): void
    {
        $resi = $this->makeResi('I', ['BNDL01' => 2, 'TRIP1' => 3]);

        $this->actingAs($this->user)
            ->getJson(route('admin.inbound.returns.lookup-resi', ['code' => $resi->no_resi]))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.item_id', $this->trip->id)
            ->assertJsonPath('items.0.qty_resi', 23)
            ->assertJsonPath('items.0.from_bundle', 'BNDL01 x2');
    }

    public function test_legacy_inbound_return_with_bundle_line_is_finalized_into_components(): void
    {
        $tx = InboundTransaction::create([
            'code' => 'INB-RET-LEGACY',
            'type' => 'return',
            'transacted_at' => now(),
            'created_by' => $this->user->id,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->user->id,
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $tx->id,
            'item_id' => $this->bundle->id,
            'qty' => 2,
            'qty_resi' => 2,
            'qty_received' => 2,
            'qty_good' => 1,
            'qty_damaged' => 1,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('admin.inbound.returns.finalize', $tx->id))
            ->assertOk();

        $this->assertSame(45, $this->tripStock());
        $this->assertSame(10, (int) \App\Models\DamagedItemStock::where('item_id', $this->trip->id)->value('stock'));
        $this->assertNull(ItemStock::where('item_id', $this->bundle->id)->value('stock'));
        $this->assertDatabaseHas('damaged_good_items', ['item_id' => $this->trip->id, 'qty' => 10]);
    }

    public function test_picking_exception_return_for_bundle_restores_component_stock(): void
    {
        $date = now()->toDateString();
        DB::table('picking_list_exceptions')->insert([
            'list_date' => $date, 'sku' => 'BNDL01', 'qty' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('qc_transit_items')->insert([
            'item_id' => $this->bundle->id,
            'transit_date' => $date,
            'qty' => 1,
            'remaining_qty' => 1,
            'last_qc_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('admin.inventory.picking-list.exception-return'), [
                'list_date' => $date,
                'sku' => 'BNDL01',
                'qty' => 1,
            ])
            ->assertOk();

        $this->assertSame(45, $this->tripStock());
        $this->assertNull(ItemStock::where('item_id', $this->bundle->id)->value('stock'));
    }

    public function test_picking_list_contains_physical_items_with_bundle_source_info(): void
    {
        $this->makeResi('J', ['BNDL01' => 3, 'TRIP1' => 2]);

        $this->actingAs($this->user)
            ->getJson(route('picker.picking-list.data'))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.sku', 'TRIP1')
            ->assertJsonPath('items.0.qty', 32)
            ->assertJsonPath('items.0.bundle_sources', 'BNDL01 x3 = 30');
    }

    public function test_recalculate_builds_physical_picking_list_and_counts_component_scans(): void
    {
        $resi = $this->makeResi('M', ['BNDL01' => 2, 'TRIP1' => 1]);
        $this->scan($resi, 'TRIP1', 1)->assertOk();   // baris TRIP1 langsung
        $this->scan($resi, 'TRIP1', 12)->assertOk();  // 12 komponen bundle (1 bundle lengkap + 2)

        DB::table('picking_lists')->delete();
        $this->actingAs($this->user)
            ->postJson(route('admin.inventory.picking-list.recalculate'), ['list_date' => now()->toDateString()])
            ->assertOk();

        $this->assertNull(PickingList::where('sku', 'BNDL01')->first());
        $row = PickingList::where('sku', 'TRIP1')->firstOrFail();
        $this->assertSame(21, (int) $row->qty);
        $this->assertSame(8, (int) $row->remaining_qty);
        $this->assertSame(0, DB::table('picking_list_exceptions')->count());
    }

    public function test_cancel_removes_component_demand_from_picking_list(): void
    {
        $this->makeResi('N', ['TRIP1' => 3]);
        $resi = $this->makeResi('O', ['BNDL01' => 1]);
        $this->scan($resi, 'TRIP1', 4)->assertOk();

        $this->actingAs($this->user)
            ->postJson(route('admin.inventory.resi-import.cancel'), [
                'id_pesanan' => $resi->id_pesanan,
                'reason' => 'Batal',
            ])
            ->assertOk();

        $row = PickingList::where('sku', 'TRIP1')->firstOrFail();
        $this->assertSame(3, (int) $row->qty);
        $this->assertSame(3, (int) $row->remaining_qty);
        $this->assertSame(0, DB::table('picking_list_exceptions')->count());
        $this->assertSame(35, $this->tripStock());
    }

    public function test_component_of_active_bundle_cannot_be_deactivated(): void
    {
        try {
            $this->trip->update(['is_active' => false]);
            $this->fail('Komponen bundle aktif seharusnya tidak bisa dinonaktifkan.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_active', $e->errors());
        }
        $this->assertTrue((bool) $this->trip->fresh()->is_active);

        // Setelah bundle dinonaktifkan, komponen boleh dinonaktifkan.
        $this->bundle->update(['is_active' => false]);
        $this->trip->update(['is_active' => false]);
        $this->assertFalse((bool) $this->trip->fresh()->is_active);
    }

    public function test_manual_picking_qty_rejects_bundle_sku(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('admin.inventory.picking-list.store-qty'), [
                'list_date' => now()->toDateString(),
                'sku' => 'BNDL01',
                'qty' => 1,
                'mode' => 'add',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
    }

    public function test_bundle_audit_command_runs_read_only(): void
    {
        $this->artisan('bundle:audit')->assertSuccessful();
        $this->assertSame(35, $this->tripStock());
    }

    private function makeResi(string $suffix, array $lines): Resi
    {
        $resi = Resi::create([
            'id_pesanan' => "ORDER-{$suffix}",
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => "TRACK-{$suffix}",
            'uploader_id' => $this->user->id,
        ]);

        foreach ($lines as $sku => $qty) {
            ResiDetail::create(['resi_id' => $resi->id, 'sku' => $sku, 'qty' => $qty]);
        }

        // Sama seperti import resi: picking list berisi barang fisik (bundle dipecah ke komponen).
        // list_date disimpan sebagai DATE murni (seperti kolom date di MySQL), bukan datetime hasil cast.
        $date = now()->toDateString();
        $physical = PickingDemand::physicalTotals(collect($lines)->map(fn ($qty, $sku) => ['sku' => $sku, 'qty' => $qty]));
        foreach ($physical as $sku => $qty) {
            $existing = DB::table('picking_lists')->where('list_date', $date)->where('sku', $sku)->first();
            if ($existing) {
                DB::table('picking_lists')->where('id', $existing->id)->update([
                    'qty' => $existing->qty + $qty,
                    'remaining_qty' => $existing->remaining_qty + $qty,
                ]);
                continue;
            }
            DB::table('picking_lists')->insert([
                'list_date' => $date,
                'sku' => $sku,
                'qty' => $qty,
                'remaining_qty' => $qty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $resi;
    }

    private function scan(Resi $resi, string $code, int $qty, ?string $requestId = null)
    {
        return $this->actingAs($this->user)->postJson(route('qc.scan-item'), array_filter([
            'resi_id' => $resi->id,
            'code' => $code,
            'qty' => $qty,
            'request_id' => $requestId,
        ]));
    }

    private function tripStock(): int
    {
        return (int) ItemStock::where('item_id', $this->trip->id)->value('stock');
    }
}
