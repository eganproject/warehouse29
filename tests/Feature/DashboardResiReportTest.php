<?php

namespace Tests\Feature;

use App\Models\Kurir;
use App\Models\PackerScanOut;
use App\Models\QcScanResi;
use App\Models\Resi;
use App\Models\User;
use App\Support\ResiReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardResiReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_counts_each_resi_once_and_excludes_canceled_scan_out_from_completion(): void
    {
        $user = User::factory()->create();
        $pending = $this->resi($user, 'PENDING');
        $pending->details()->createMany([['sku' => 'A', 'qty' => 2], ['sku' => 'B', 'qty' => 3]]);
        $progress = $this->resi($user, 'PROGRESS');
        $ready = $this->resi($user, 'READY');
        $scanned = $this->resi($user, 'SCANNED');
        $canceled = $this->resi($user, 'CANCELED', ['status' => 'canceled']);
        foreach ([$progress, $ready, $scanned, $canceled] as $resi) {
            QcScanResi::create([
                'resi_id' => $resi->id, 'scanned_by' => $user->id,
                'status' => $resi->id === $progress->id ? 'in_progress' : 'completed',
            ]);
        }
        foreach ([$scanned, $canceled] as $resi) {
            PackerScanOut::create([
                'resi_id' => $resi->id, 'scanned_by' => $user->id,
                'scan_type' => 'resi', 'scan_code' => $resi->no_resi,
                'scan_date' => '2026-09-07', 'scanned_at' => '2026-09-07 10:00:00',
            ]);
        }
        $this->resi($user, 'OUTSIDE', ['tanggal_upload' => '2026-09-08']);
        $report = app(ResiReport::class)->build(['report_start' => '2026-09-05', 'report_end' => '2026-09-07']);

        $this->assertEquals(5, $report['summary']->total);
        $this->assertEquals(5, $report['summary']->active_qty);
        foreach (array_keys(ResiReport::STAGES) as $stage) {
            $this->assertEquals(1, $report['summary']->{$stage});
        }
        $this->assertCount(3, $report['daily']);
        $this->assertEquals(0, $report['daily'][1]->total);
        $this->assertEquals(5, $report['daily'][0]->total);
        $this->assertEquals(5, $report['couriers']->sum('total'));
        $this->assertSame('Tanpa kurir', $report['couriers']->first()->courier_name);
        $this->assertSame($pending->id, $report['details']->first()->id);
    }

    public function test_courier_filter_applies_to_all_data_while_stage_filter_only_limits_details(): void
    {
        $user = User::factory()->create();
        $courier = Kurir::create(['name' => 'Kurir A']);
        $this->resi($user, 'A', ['kurir_id' => $courier->id]);
        $this->resi($user, 'B', ['kurir_id' => $courier->id, 'status' => 'canceled']);
        $this->resi($user, 'C');
        $report = app(ResiReport::class)->build([
            'report_start' => '2026-09-05', 'report_end' => '2026-09-05',
            'report_kurir' => $courier->id, 'report_status' => 'canceled',
        ]);
        $this->assertEquals(2, $report['summary']->total);
        $this->assertEquals(2, $report['daily']->sum('total'));
        $this->assertEquals(2, $report['couriers']->sum('total'));
        $this->assertSame(1, $report['details']->total());
        $this->assertSame('canceled', $report['details']->first()->stage);
    }

    public function test_dashboard_renders_empty_report_and_rejects_invalid_dates(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('dashboard', ['tab' => 'report', 'report_start' => '2026-09-05', 'report_end' => '2026-09-07']))
            ->assertOk()->assertSee('Laporan Resi')->assertSee('Tidak ada resi pada periode');
        foreach ([
            ['report_start' => '2026-09-07', 'report_end' => '2026-09-05'],
            ['report_start' => '2026-01-01', 'report_end' => '2027-01-02'],
            ['report_start' => 'invalid', 'report_end' => '2026-09-05'],
        ] as $filters) {
            $this->getJson(route('dashboard', $filters))->assertUnprocessable();
        }
    }

    public function test_pagination_preserves_report_filters_and_active_tab(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 26; $i++) {
            $this->resi($user, 'PAGE-'.$i);
        }
        $this->actingAs($user)->get(route('dashboard', [
            'tab' => 'report', 'report_start' => '2026-09-05', 'report_end' => '2026-09-05',
            'report_status' => 'pending', 'report_page' => 2,
        ]))->assertOk()->assertViewHas('report', function ($report) {
            $this->assertCount(1, $report['details']);
            $this->assertStringContainsString('tab=report', $report['details']->previousPageUrl());
            $this->assertStringContainsString('report_status=pending', $report['details']->previousPageUrl());

            return true;
        });
    }

    private function resi(User $user, string $code, array $attributes = []): Resi
    {
        return Resi::create(array_merge([
            'id_pesanan' => $code, 'no_resi' => $code, 'uploader_id' => $user->id,
            'kurir_id' => null,
            'tanggal_pesanan' => '2026-09-05', 'tanggal_upload' => '2026-09-05',
        ], $attributes));
    }
}
