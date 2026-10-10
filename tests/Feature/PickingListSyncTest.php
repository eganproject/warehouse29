<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemStock;
use App\Models\PickingList;
use App\Models\QcTransitItem;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\User;
use App\Support\PickingDemand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Picking list harus selalu sama dengan: kebutuhan resi aktif - barang yang sudah di-QC,
 * termasuk setelah resi dibatalkan pada tahap apa pun.
 */
class PickingListSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Item $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'QC User',
            'email' => 'qc-sync@example.test',
            'password' => 'password',
        ]);
        $this->trip = Item::create(['sku' => 'TRIP1', 'name' => 'Trip satuan']);
        ItemStock::create(['item_id' => $this->trip->id, 'stock' => 100]);
    }

    public function test_cancel_after_scan_out_keeps_picking_list_for_other_resis(): void
    {
        // Transit hari sebelumnya yang sudah habis discan out.
        $this->oldTransit(50, 0);

        $canceled = $this->makeResi('A', ['TRIP1' => 5]);
        $other = $this->makeResi('B', ['TRIP1' => 3]);

        $this->scan($canceled, 'TRIP1', 5)->assertOk();
        $this->scanOut($canceled)->assertOk();
        $this->cancel($canceled, ['confirm_stock_returned' => true])->assertOk();

        $this->assertPicking(3, 3);
        $this->assertSame(0, DB::table('picking_list_exceptions')->count());
        $this->assertSame(0, (int) QcTransitItem::whereDate('transit_date', now()->toDateString())->sum('qty'));
        $this->assertSame(50, (int) QcTransitItem::whereDate('transit_date', now()->subDay()->toDateString())->value('qty'));

        // Resi lain yang masih berisi TRIP1 tetap bisa di-QC.
        $this->scan($other, 'TRIP1', 3)->assertOk();
        $this->assertPicking(3, 0);
    }

    public function test_cancel_after_qc_with_older_transit_keeps_transit_totals(): void
    {
        // Sisa transit kemarin masih ada, sehingga scan out hari ini memakai transit kemarin dulu (FIFO)
        // dan sisa transit hari ini tidak cukup untuk dibalik dari tanggal QC saja.
        $this->oldTransit(4, 4);

        $shipped = $this->makeResi('C', ['TRIP1' => 6]);
        $canceled = $this->makeResi('D', ['TRIP1' => 2]);
        $other = $this->makeResi('E', ['TRIP1' => 2]);

        $this->scan($shipped, 'TRIP1', 6)->assertOk();
        $this->scan($canceled, 'TRIP1', 2)->assertOk();
        $this->scanOut($shipped)->assertOk();
        $this->cancel($canceled)->assertOk();

        $this->assertPicking(8, 2);
        $this->assertSame(0, DB::table('picking_list_exceptions')->count());
        $this->assertSame(6, (int) QcTransitItem::whereDate('transit_date', now()->toDateString())->value('qty'));
        // Total sisa transit tetap = barang QC yang belum discan out (resi C sudah keluar, resi D batal).
        $this->assertSame(4, (int) QcTransitItem::sum('remaining_qty'));

        $this->scan($other, 'TRIP1', 2)->assertOk();
        $this->assertPicking(8, 0);
    }

    public function test_cancel_partial_bundle_qc_keeps_picking_list_in_sync(): void
    {
        $bundle = Item::create(['sku' => 'BNDL01', 'name' => 'Bundle Trip isi 10', 'is_bundle' => true]);
        ItemBundle::create(['bundle_item_id' => $bundle->id, 'component_item_id' => $this->trip->id, 'qty' => 10]);

        $canceled = $this->makeResi('F', ['BNDL01' => 2]);
        $other = $this->makeResi('G', ['TRIP1' => 4]);

        $this->scan($canceled, 'TRIP1', 13)->assertOk();
        $this->scan($other, 'TRIP1', 1)->assertOk();
        $this->cancel($canceled)->assertOk();

        $this->assertPicking(4, 3);
        $this->scan($other, 'TRIP1', 3)->assertOk();
        $this->assertPicking(4, 0);
    }

    public function test_recalculate_repairs_picking_list_even_when_transit_qty_drifted(): void
    {
        $resi = $this->makeResi('H', ['TRIP1' => 5]);
        $this->makeResi('I', ['TRIP1' => 3]);
        $this->scan($resi, 'TRIP1', 5)->assertOk();

        // Data lama: qty transit hari ini terlanjur salah (bug pembalikan transit sebelumnya).
        QcTransitItem::whereDate('transit_date', now()->toDateString())->update(['qty' => 9]);
        DB::table('picking_lists')->update(['remaining_qty' => 0]);

        $this->actingAs($this->user)
            ->postJson(route('admin.inventory.picking-list.recalculate'), ['list_date' => now()->toDateString()])
            ->assertOk();

        $this->assertPicking(8, 3);
        $this->assertSame(0, DB::table('picking_list_exceptions')->count());
    }

    public function test_returned_exception_is_not_recreated_by_recalculate(): void
    {
        $resi = $this->makeResi('J', ['TRIP1' => 5]);
        $this->scan($resi, 'TRIP1', 5)->assertOk();

        // Admin mengurangi picking list sehingga 2 pcs menjadi exception, lalu diretur ke stok.
        $this->actingAs($this->user)
            ->postJson(route('admin.inventory.picking-list.store-qty'), [
                'list_date' => now()->toDateString(),
                'sku' => 'TRIP1',
                'qty' => 2,
                'mode' => 'reduce',
            ])
            ->assertOk();
        $this->assertDatabaseHas('picking_list_exceptions', ['sku' => 'TRIP1', 'qty' => 2]);

        $this->actingAs($this->user)
            ->postJson(route('admin.inventory.picking-list.exception-return'), [
                'list_date' => now()->toDateString(),
                'sku' => 'TRIP1',
                'qty' => 2,
            ])
            ->assertOk();
        $this->assertSame(0, DB::table('picking_list_exceptions')->count());
        $this->assertSame(97, (int) ItemStock::where('item_id', $this->trip->id)->value('stock'));

        $this->actingAs($this->user)
            ->postJson(route('admin.inventory.picking-list.store-qty'), [
                'list_date' => now()->toDateString(),
                'sku' => 'TRIP1',
                'qty' => 1,
                'mode' => 'add',
            ])
            ->assertOk();

        $this->assertPicking(4, 1);
        $this->assertSame(0, DB::table('picking_list_exceptions')->count());
    }

    private function assertPicking(int $qty, int $remaining): void
    {
        $row = PickingList::where('sku', 'TRIP1')->firstOrFail();
        $this->assertSame(
            [$qty, $remaining],
            [(int) $row->qty, (int) $row->remaining_qty],
            'Picking list TRIP1 [qty, remaining] tidak sesuai.'
        );
    }

    private function oldTransit(int $qty, int $remaining): void
    {
        QcTransitItem::create([
            'item_id' => $this->trip->id,
            'transit_date' => now()->subDay()->toDateString(),
            'qty' => $qty,
            'remaining_qty' => $remaining,
            'last_qc_at' => now()->subDay(),
        ]);
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

        // Sama seperti import resi: picking list berisi barang fisik.
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

    private function scan(Resi $resi, string $code, int $qty)
    {
        return $this->actingAs($this->user)->postJson(route('qc.scan-item'), [
            'resi_id' => $resi->id,
            'code' => $code,
            'qty' => $qty,
        ]);
    }

    private function scanOut(Resi $resi)
    {
        return $this->actingAs($this->user)->postJson(route('picker.scan-out.scan'), [
            'type' => 'no_resi',
            'code' => $resi->no_resi,
        ]);
    }

    private function cancel(Resi $resi, array $extra = [])
    {
        return $this->actingAs($this->user)->postJson(route('admin.inventory.resi-import.cancel'), [
            'id_pesanan' => $resi->id_pesanan,
            'reason' => 'Dibatalkan pembeli',
            ...$extra,
        ]);
    }
}
