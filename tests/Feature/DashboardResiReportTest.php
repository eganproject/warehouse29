<?php

namespace Tests\Feature;

use App\Models\Resi;
use App\Models\User;
use App\Support\ResiReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardResiReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_average_includes_quiet_days_and_excludes_cancellations(): void
    {
        $user = User::factory()->create();
        $resi = $this->resi($user, 'A', '2026-09-05');
        $resi->details()->createMany([['sku' => 'A', 'qty' => 2], ['sku' => 'B', 'qty' => 3]]);
        $this->resi($user, 'B', '2026-09-05', 'active');
        $this->resi($user, 'C', '2026-09-05', 'canceled');
        $this->resi($user, 'D', '2026-09-07');
        $this->resi($user, 'BEFORE', '2026-09-04');
        $this->resi($user, 'AFTER', '2026-09-08');

        $report = app(ResiReport::class)->build(['report_start' => '2026-09-05', 'report_end' => '2026-09-07']);
        $summary = $report['summary'];
        $this->assertSame(3, $summary->active);
        $this->assertSame(1, $summary->canceled);
        $this->assertSame(3, $summary->days);
        $this->assertEquals(1, $summary->average);
        $this->assertSame(2, $summary->highest);
        $this->assertSame('2026-09-05', $summary->peak_date);
        $this->assertSame([2, 0, 1], $report['daily']->pluck('active')->all());
        $this->assertSame([1, 0, 0], $report['daily']->pluck('canceled')->all());
    }

    public function test_peak_ties_and_single_day_period(): void
    {
        $user = User::factory()->create();
        $this->resi($user, 'A', '2026-09-05');
        $this->resi($user, 'B', '2026-09-07');
        $report = app(ResiReport::class)->build(['report_start' => '2026-09-05', 'report_end' => '2026-09-07']);
        $this->assertSame(2, $report['summary']->peak_days);
        $this->assertSame('2026-09-05', $report['summary']->peak_date);
        $this->assertEqualsWithDelta(2 / 3, $report['summary']->average, 0.0001);

        $report = app(ResiReport::class)->build(['report_start' => '2026-09-07', 'report_end' => '2026-09-07']);
        $this->assertSame(1, $report['summary']->days);
        $this->assertSame(1, $report['summary']->active);
        $this->assertEquals(1, $report['summary']->average);
    }

    public function test_empty_and_canceled_only_periods_have_no_peak_date(): void
    {
        $filters = ['report_start' => '2026-09-05', 'report_end' => '2026-09-07'];
        $report = app(ResiReport::class)->build($filters);
        $this->assertSame(0, $report['summary']->active);
        $this->assertEquals(0, $report['summary']->average);
        $this->assertSame(0, $report['summary']->highest);
        $this->assertNull($report['summary']->peak_date);
        $this->assertCount(3, $report['daily']);

        $this->resi(User::factory()->create(), 'CANCELED', '2026-09-05', 'canceled');
        $report = app(ResiReport::class)->build($filters);
        $this->assertSame(1, $report['summary']->canceled);
        $this->assertEquals(0, $report['summary']->average);
        $this->assertNull($report['summary']->peak_date);
    }

    public function test_dashboard_renders_simple_report_and_rejects_invalid_dates(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('dashboard', ['tab' => 'report', 'report_start' => '2026-09-05', 'report_end' => '2026-09-07']))
            ->assertOk()->assertSee('Rata-rata Resi per Hari')->assertSee('Tidak ada resi pada periode')
            ->assertSee('Jumlah Tertinggi')->assertSee('Rincian Resi Harian')
            ->assertDontSee('Rekap per Kurir')->assertDontSee('name="report_kurir"', false);
        foreach ([
            ['report_start' => '2026-09-07', 'report_end' => '2026-09-05'],
            ['report_start' => '2026-01-01', 'report_end' => '2027-01-02'],
            ['report_start' => 'invalid', 'report_end' => '2026-09-05'],
        ] as $filters) {
            $this->getJson(route('dashboard', $filters))->assertUnprocessable();
        }
    }

    public function test_default_period_is_month_to_date_and_legacy_filters_do_not_hide_resis(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 7));
        $user = User::factory()->create();
        $this->resi($user, 'A', '2026-09-05');
        $this->actingAs($user)->get(route('dashboard', [
            'tab' => 'report', 'report_kurir' => 999, 'report_status' => 'canceled',
        ]))->assertOk()->assertViewHas('report', function ($report) {
            $this->assertSame(7, $report['summary']->days);
            $this->assertSame(1, $report['summary']->active);
            $this->assertSame('2026-09-01', $report['daily']->first()->date);
            $this->assertSame('2026-09-07', $report['daily']->last()->date);

            return true;
        });
    }

    private function resi(User $user, string $code, string $date, string $status = 'active'): Resi
    {
        return Resi::create([
            'id_pesanan' => $code, 'no_resi' => $code, 'uploader_id' => $user->id,
            'kurir_id' => null, 'status' => $status,
            'tanggal_pesanan' => $date, 'tanggal_upload' => $date,
        ]);
    }
}
