<?php

namespace Tests\Feature;

use App\Exports\TokoReportExport;
use App\Models\Channel;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\Kurir;
use App\Models\PackerScanOut;
use App\Models\QcScanResi;
use App\Models\Resi;
use App\Models\Toko;
use App\Models\User;
use App\Support\StockPeriodReport;
use App\Support\TokoReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class TokoReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Toko $alpha;

    private Toko $beta;

    private Kurir $kurir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->alpha = Toko::create(['name' => 'Toko Alpha']);
        $this->beta = Toko::create(['name' => 'Toko Beta']);
        $this->kurir = Kurir::create(['name' => 'Kurir Uji']);
        $shopee = Channel::create(['name' => 'Shopee']);

        // Alpha: 2 aktif (1 sudah scan out) + 1 batal. Beta: 1 aktif. Tanpa toko: 1 aktif.
        $a1 = $this->resi('A1', $this->alpha, $shopee);
        $a1->details()->create(['sku' => 'SKU-1', 'qty' => 3]);
        $this->resi('A2', $this->alpha, $shopee)->details()->create(['sku' => 'SKU-2', 'qty' => 1]);
        $this->resi('A3', $this->alpha, $shopee, 'canceled');
        $b1 = $this->resi('B1', $this->beta);
        $this->resi('N1', null);
        $this->resi('OLD', $this->alpha, null, null, '2026-09-01');

        QcScanResi::create(['resi_id' => $a1->id, 'status' => 'completed', 'scanned_at' => '2026-09-05 08:00:00', 'scanned_by' => $this->user->id, 'completed_at' => '2026-09-05 08:05:00']);
        QcScanResi::create(['resi_id' => $b1->id, 'status' => 'in_progress', 'scanned_at' => '2026-09-05 09:00:00', 'scanned_by' => $this->user->id]);
        PackerScanOut::create([
            'resi_id' => $a1->id, 'scan_type' => 'no_resi', 'scan_code' => 'A1',
            'scan_date' => '2026-09-05', 'scanned_at' => '2026-09-05 10:00:00', 'scanned_by' => $this->user->id,
        ]);
    }

    public function test_toko_report_aggregates_resi_per_toko(): void
    {
        $filters = TokoReport::filters(['date_from' => '2026-09-05', 'date_to' => '2026-09-05']);
        $rows = TokoReport::perToko($filters)->keyBy('toko');

        $alpha = $rows['Toko Alpha'];
        $this->assertSame(2, $alpha['total_resi']);
        $this->assertSame(1, $alpha['scan_total']);
        $this->assertSame(1, $alpha['remaining']);
        $this->assertSame(1, $alpha['canceled_total']);
        $this->assertSame(1, $alpha['qc_total']);
        $this->assertSame(4, $alpha['total_qty']);
        $this->assertSame('Shopee', $alpha['channels']);
        $this->assertEquals(50.0, $alpha['completion_rate']);
        $this->assertSame(1, $rows['Toko Beta']['total_resi']);
        $this->assertSame(1, $rows['Tanpa Toko']['total_resi']);

        $summary = TokoReport::summary(TokoReport::perToko($filters));
        $this->assertSame(2, $summary['toko_count']);
        $this->assertSame(4, $summary['total_resi']);
        $this->assertSame('Toko Alpha', $summary['top_toko']);
        $this->assertSame(1, $summary['without_toko']);

        $onlyNone = TokoReport::perToko(TokoReport::filters([
            'date_from' => '2026-09-05', 'date_to' => '2026-09-05', 'toko_id' => 'none',
        ]));
        $this->assertSame(['Tanpa Toko'], $onlyNone->pluck('toko')->all());
    }

    public function test_toko_report_page_data_and_export(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.reports.toko.index'))
            ->assertOk()
            ->assertSee('Rekap per Toko')
            ->assertSee('Toko Alpha');

        $this->actingAs($this->user)
            ->getJson(route('admin.reports.toko.data', ['date_from' => '2026-09-05', 'date_to' => '2026-09-05', 'toko_id' => $this->beta->id]))
            ->assertOk()
            ->assertJsonPath('summary.total_resi', 1)
            ->assertJsonPath('data.0.toko', 'Toko Beta');

        Excel::fake();
        $this->actingAs($this->user)
            ->get(route('admin.reports.toko.export', ['date_from' => '2026-09-05', 'date_to' => '2026-09-05']))
            ->assertOk();
        Excel::assertDownloaded('laporan-toko-2026-09-05-2026-09-05.xlsx');
    }

    public function test_toko_report_workbook_contents(): void
    {
        $filters = TokoReport::filters(['date_from' => '2026-09-05', 'date_to' => '2026-09-05']);
        $path = tempnam(sys_get_temp_dir(), 'toko-report-');
        file_put_contents($path, Excel::raw(new TokoReportExport($filters, 'Penguji'), \Maatwebsite\Excel\Excel::XLSX));

        try {
            $spreadsheet = IOFactory::load($path);
            $this->assertSame(
                ['Ringkasan', 'Ringkasan Toko', 'Tren Harian per Toko', 'Detail Resi'],
                $spreadsheet->getSheetNames()
            );
            $summary = $spreadsheet->getSheetByName('Ringkasan Toko');
            $this->assertSame('Toko Alpha', $summary->getCell('A2')->getValue());
            $this->assertSame(2, $summary->getCell('C2')->getValue());

            $detail = $spreadsheet->getSheetByName('Detail Resi');
            $this->assertSame(6, $detail->getHighestRow()); // header + 5 resi pada 5 Sept
            $values = collect($detail->toArray())->skip(1);
            $a1 = $values->firstWhere(5, 'A1');
            $this->assertSame('Toko Alpha', $a1[2]);
            $this->assertSame('Shopee', $a1[3]);
            $this->assertSame('Sudah Scan Out', $a1[8]);
            $this->assertSame('Tanpa Toko', $values->firstWhere(5, 'N1')[2]);
            $this->assertSame('QC Berjalan', $values->firstWhere(5, 'B1')[8]);
        } finally {
            @unlink($path);
        }
    }

    public function test_courier_and_scan_out_reports_filter_by_toko(): void
    {
        $this->actingAs($this->user)
            // SQLite menyimpan kolom date beserta jam, jadi batas akhir dibuat 6 Sept (MySQL tidak terpengaruh).
            ->getJson(route('admin.reports.couriers.data', ['date_from' => '2026-09-05', 'date_to' => '2026-09-06', 'toko_id' => $this->alpha->id]))
            ->assertOk()
            ->assertJsonPath('summary.total_resi', 2)
            ->assertJsonPath('summary.scan_total', 1);

        $this->actingAs($this->user)
            ->getJson(route('admin.reports.packer-reports.data', ['date_from' => '2026-09-05', 'date_to' => '2026-09-05', 'toko_id' => $this->beta->id]))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('comparison.import_total', 1)
            ->assertJsonPath('comparison.missing_samples.0.toko', 'Toko Beta');

        $response = $this->actingAs($this->user)
            ->getJson(route('admin.reports.packer-reports.data', ['date_from' => '2026-09-05', 'date_to' => '2026-09-05']))
            ->assertOk()
            ->assertJsonPath('comparison.import_total', 4);
        $perToko = collect($response->json('comparison.per_toko'))->keyBy('toko');
        $this->assertSame(1, $perToko['Toko Alpha']['missing_total']);
        $this->assertSame(1, $perToko['Toko Alpha']['canceled_total']);

        $this->actingAs($this->user)
            ->getJson(route('admin.outbound.packer-scan-outs.data', ['date_from' => '2026-09-05', 'date_to' => '2026-09-05', 'toko_id' => $this->alpha->id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.toko', 'Toko Alpha')
            ->assertJsonPath('data.0.channel', 'Shopee');
        $this->actingAs($this->user)
            ->getJson(route('admin.outbound.packer-scan-outs.data', ['date_from' => '2026-09-05', 'date_to' => '2026-09-05', 'toko_id' => $this->beta->id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 0);
    }

    public function test_qc_history_and_detail_filter_by_toko(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('admin.outbound.qc-scan-history.data', ['date_from' => '2026-09-05', 'date_to' => '2026-09-05', 'toko_id' => $this->beta->id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.toko', 'Toko Beta');

        $this->actingAs($this->user)
            ->getJson(route('admin.outbound.picker-reports.detail', ['date' => '2026-09-05', 'user_id' => $this->user->id, 'toko_id' => $this->alpha->id]))
            ->assertOk()
            ->assertJsonPath('total_resi', 1)
            ->assertJsonPath('resis.0.toko', 'Toko Alpha');
    }

    public function test_return_report_filters_and_ranks_by_toko(): void
    {
        $item = Item::create(['sku' => 'RET-TOKO', 'name' => 'Produk Retur']);
        $alphaResi = Resi::where('id_pesanan', 'A1')->firstOrFail();
        foreach ([[$alphaResi->id, 'RET-1', 4], [null, 'RET-2', 2]] as [$resiId, $code, $qty]) {
            $transaction = InboundTransaction::create([
                'code' => $code, 'type' => 'return', 'resi_id' => $resiId,
                'transacted_at' => now(), 'status' => 'pending', 'created_by' => $this->user->id,
            ]);
            $transaction->items()->create(['item_id' => $item->id, 'qty' => $qty, 'qty_resi' => $qty, 'qty_received' => $qty, 'qty_good' => $qty]);
        }

        $all = $this->actingAs($this->user)
            ->getJson(route('admin.reports.returns.data', ['direction' => 'inbound']))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 2);
        $tokos = collect($all->json('analytics.tokos'))->keyBy('label');
        $this->assertSame(4, $tokos['Toko Alpha']['secondary']);
        $this->assertSame(1, $tokos['Tanpa toko']['value']);

        $this->actingAs($this->user)
            ->getJson(route('admin.reports.returns.data', ['direction' => 'inbound', 'toko_id' => $this->alpha->id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.toko', 'Toko Alpha');

        $this->actingAs($this->user)
            ->getJson(route('admin.reports.returns.data', ['direction' => 'inbound', 'toko_id' => 'none']))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.code', 'RET-2');
    }

    public function test_stock_movement_reports_follow_toko_from_resi_source(): void
    {
        $item = Item::create(['sku' => 'STK-1', 'name' => 'Barang Stok', 'is_active' => true, 'is_bundle' => false, 'category_id' => 0]);
        $qcAlpha = QcScanResi::whereHas('resi', fn ($q) => $q->where('id_pesanan', 'A1'))->value('id');
        $qcBeta = QcScanResi::whereHas('resi', fn ($q) => $q->where('id_pesanan', 'B1'))->value('id');
        foreach ([
            ['in', 100, 'inbound', 999, '2026-09-04 08:00:00'],
            ['out', 3, 'qc_resi', $qcAlpha, '2026-09-05 08:05:00'],
            ['out', 2, 'qc_resi', $qcBeta, '2026-09-05 09:05:00'],
            ['out', 1, 'outbound', 1, '2026-09-05 10:00:00'],
        ] as [$direction, $qty, $type, $sourceId, $at]) {
            DB::table('stock_mutations')->insert([
                'item_id' => $item->id, 'direction' => $direction, 'qty' => $qty,
                'source_type' => $type, 'source_id' => $sourceId, 'occurred_at' => $at,
            ]);
        }

        // Halaman mutasi stok: filter & kolom toko.
        $this->actingAs($this->user)
            ->getJson(route('admin.inventory.stock-mutations.data', ['date_from' => '2026-09-05', 'date_to' => '2026-09-05', 'toko_id' => $this->alpha->id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.toko', 'Toko Alpha')
            ->assertJsonPath('data.0.channel', 'Shopee');
        $this->actingAs($this->user)
            ->getJson(route('admin.inventory.stock-mutations.data', ['date_from' => '2026-09-04', 'date_to' => '2026-09-05', 'toko_id' => 'none']))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 2);

        // Laporan mutasi stok (rekap sumber) dengan filter toko.
        $this->actingAs($this->user)
            ->getJson(route('admin.reports.stock-mutations.data', ['date_from' => '2026-09-05', 'date_to' => '2026-09-05', 'toko_id' => $this->beta->id]))
            ->assertOk()
            ->assertJsonPath('summary.total_out', 2);

        // Pergerakan barang: qty keluar mengikuti toko, stok akhir tetap stok gudang.
        $report = app(StockPeriodReport::class);
        $filters = ['date_from' => '2026-09-01', 'date_to' => '2026-09-05', 'stock_type' => 'regular', 'tab' => 'movement', 'toko_id' => (string) $this->alpha->id];
        $row = $report->query($filters)->where('sku', 'STK-1')->first();
        $this->assertEquals(3, $row->qty_out);
        $this->assertEquals(1, $row->outgoing_frequency);
        $this->assertEquals(94, $row->closing);
        $this->assertSame([0, 0, 0, 0, 3], $report->dailyTrend($filters)['qty_out']);

        $perToko = $report->outgoingPerToko(array_merge($filters, ['toko_id' => '']))->keyBy('toko');
        $this->assertEquals(3, $perToko['Toko Alpha']->qty_out);
        $this->assertEquals(2, $perToko['Toko Beta']->qty_out);
        $this->assertEquals(1, $perToko['Bukan dari resi (manual/lainnya)']->qty_out);

        // Tab saldo mengabaikan filter toko agar awal + in − out = akhir.
        $balance = $report->query(array_merge($filters, ['tab' => 'balance']))->where('sku', 'STK-1')->first();
        $this->assertEquals(6, $balance->qty_out);

        $this->actingAs($this->user)
            ->get(route('admin.reports.stock-as-of.index', $filters))
            ->assertOk()
            ->assertSee('Filter toko aktif');
    }

    private function resi(string $code, ?Toko $toko, ?Channel $channel = null, ?string $status = null, string $date = '2026-09-05'): Resi
    {
        return Resi::create([
            'id_pesanan' => $code, 'no_resi' => $code, 'uploader_id' => $this->user->id,
            'kurir_id' => $this->kurir->id, 'toko_id' => $toko?->id, 'channel_id' => $channel?->id,
            'status' => $status ?? 'active', 'tanggal_pesanan' => $date, 'tanggal_upload' => $date,
        ]);
    }
}
