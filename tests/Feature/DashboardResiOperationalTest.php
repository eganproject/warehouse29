<?php

namespace Tests\Feature;

use App\Models\Kurir;
use App\Models\PackerScanOut;
use App\Models\QcScanResi;
use App\Models\Resi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardResiOperationalTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_kurirs_with_resis_on_selected_date_are_listed_with_pipeline_counts(): void
    {
        $user = User::factory()->create();
        $busy = Kurir::create(['name' => 'Kurir Sibuk']);
        $idle = Kurir::create(['name' => 'Kurir Kosong']);
        $done = Kurir::create(['name' => 'Kurir Selesai']);

        $scanned = $this->resi($user, 'A', '2026-09-05', $busy);
        $qcOnly = $this->resi($user, 'B', '2026-09-05', $busy);
        $this->resi($user, 'C', '2026-09-05', $busy);
        $canceledScanned = $this->resi($user, 'D', '2026-09-05', $busy, 'canceled');
        $doneResi = $this->resi($user, 'E', '2026-09-05', $done);
        $this->resi($user, 'OTHER', '2026-09-06', $idle);

        foreach ([$scanned, $qcOnly, $canceledScanned] as $resi) {
            QcScanResi::create(['resi_id' => $resi->id, 'status' => 'completed', 'scanned_by' => $user->id, 'completed_at' => now()]);
        }
        foreach ([$scanned, $canceledScanned, $doneResi] as $resi) {
            PackerScanOut::create([
                'resi_id' => $resi->id, 'scan_type' => 'resi', 'scan_code' => $resi->no_resi,
                'scan_date' => '2026-09-05', 'scanned_by' => $user->id,
            ]);
        }

        $this->actingAs($user)->get(route('dashboard', ['date' => '2026-09-05']))
            ->assertOk()
            ->assertSee('Kurir Sibuk')->assertDontSee('Kurir Kosong')
            ->assertViewHas('kurirs', function ($kurirs) {
                $this->assertSame(['Kurir Sibuk', 'Kurir Selesai'], $kurirs->pluck('name')->all());
                $busy = $kurirs->first();
                $this->assertSame(3, $busy['resi_total']);
                $this->assertSame(2, $busy['qc_total']);
                $this->assertSame(1, $busy['scan_total']);
                $this->assertSame(1, $busy['waiting_scan']);
                $this->assertSame(2, $busy['remaining']);
                $this->assertSame(1, $busy['canceled_total']);
                $this->assertSame(33, $busy['progress']);
                $this->assertSame(0, $kurirs->last()['remaining']);

                return true;
            })
            ->assertViewHas('resiSummary', function ($summary) {
                $this->assertSame(4, $summary->active);
                $this->assertSame(1, $summary->canceled);
                $this->assertSame(2, $summary->scan);
                $this->assertSame(2, $summary->remaining);
                $this->assertSame(1, $summary->waiting_scan);
                $this->assertSame(1, $summary->not_started);
                $this->assertSame(1, $summary->kurir_done);

                return true;
            });
    }

    public function test_empty_date_shows_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard', ['date' => '2026-09-05']))
            ->assertOk()
            ->assertSee('Tidak ada resi yang diupload pada tanggal ini.')
            ->assertViewHas('kurirs', fn ($kurirs) => $kurirs->isEmpty());
    }

    private function resi(User $user, string $code, string $date, Kurir $kurir, string $status = 'active'): Resi
    {
        return Resi::create([
            'id_pesanan' => $code, 'no_resi' => $code, 'uploader_id' => $user->id,
            'kurir_id' => $kurir->id, 'status' => $status,
            'tanggal_pesanan' => $date, 'tanggal_upload' => $date,
        ]);
    }
}
