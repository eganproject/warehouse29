<?php

namespace Tests\Feature;

use App\Exports\StockAsOfReportExport;
use App\Models\Item;
use App\Models\User;
use App\Support\StockPeriodReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockPeriodReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_balances_include_whole_period_and_exclude_future_mutations_and_virtual_bundles(): void
    {
        $item = $this->item('A');
        $this->mutation($item, 'in', 100, '2026-08-31 23:59:59');
        $this->mutation($item, 'out', 10, '2026-08-31 23:59:59');
        $this->mutation($item, 'in', 20, '2026-09-01 00:00:00');
        $this->mutation($item, 'out', 7, '2026-09-04 23:59:59');
        $this->mutation($item, 'out', 50, '2026-09-05 00:00:00');
        $this->item('EMPTY');
        $this->item('BUNDLE', ['is_bundle' => true]);
        $this->item('INACTIVE', ['is_active' => false]);
        $report = app(StockPeriodReport::class);
        $rows = $report->ordered($this->filters())->get();
        $this->assertCount(2, $rows);
        $this->assertEquals([90, 20, 7, 103], [$rows[0]->opening, $rows[0]->qty_in, $rows[0]->qty_out, $rows[0]->closing]);
        $this->assertEquals(0, $rows[1]->closing);
        $summary = $report->summary($this->filters());
        $this->assertEquals($summary->closing, $summary->opening + $summary->qty_in - $summary->qty_out);
        $this->assertEquals(103, $summary->closing);
    }

    public function test_movement_uses_cumulative_contribution_frequency_and_period_filter(): void
    {
        $fastOne = $this->item('FAST-1');
        $fastTwo = $this->item('FAST-2');
        $medium = $this->item('MEDIUM');
        $slow = $this->item('SLOW');
        $non = $this->item('NON');
        $this->mutation($fastOne, 'in', 100, '2026-08-31 10:00:00');
        $this->mutation($fastOne, 'out', 30, '2026-09-01 10:00:00');
        $this->mutation($fastOne, 'out', 20, '2026-09-01 11:00:00');
        $this->mutation($fastTwo, 'out', 20, '2026-09-02 10:00:00');
        $this->mutation($medium, 'out', 20, '2026-09-03 10:00:00');
        $this->mutation($slow, 'out', 10, '2026-09-04 10:00:00');
        $this->mutation($non, 'out', 4, '2026-08-30 10:00:00');
        $this->mutation($non, 'in', 10, '2026-09-02 10:00:00');
        $this->mutation($non, 'out', 100, '2026-09-05 10:00:00');
        $report = app(StockPeriodReport::class);
        $rows = $report->ordered($this->filters(['tab' => 'movement']))->get();
        $this->assertSame(['FAST-1', 'FAST-2', 'MEDIUM', 'SLOW', 'NON'], $rows->pluck('sku')->all());
        $this->assertSame(['fast', 'fast', 'medium', 'slow', 'non'], $rows->pluck('movement')->all());
        $this->assertEquals(50, $rows[0]->contribution_percent);
        $this->assertEquals(70, $rows[1]->cumulative_percent);
        $this->assertEquals(2, $rows[0]->outgoing_frequency);
        $this->assertEquals(1, $rows[0]->outgoing_days);
        $this->assertEquals(12.5, $rows[0]->average_out);
        $this->assertEquals(4, $rows[0]->days_cover);
        $this->assertSame('2026-08-30 10:00:00', $rows[4]->last_out_at);
        $filtered = $this->filters(['tab' => 'movement', 'movement' => 'medium']);
        $this->assertSame('MEDIUM', $report->query($filtered)->first()->sku);
        $this->assertEquals(1, $report->summary($filtered)->total_sku);
        $this->assertEquals(1, $report->query($this->filters(['tab' => 'movement', 'movement' => 'medium']))->count());
    }

    public function test_daily_trend_contains_every_date_and_respects_item_filters(): void
    {
        $visible = $this->item('VISIBLE');
        $hidden = $this->item('HIDDEN');
        $this->mutation($visible, 'in', 12, '2026-09-01 08:00:00');
        $this->mutation($visible, 'out', 5, '2026-09-03 09:00:00');
        $this->mutation($hidden, 'out', 20, '2026-09-02 09:00:00');

        $trend = app(StockPeriodReport::class)->dailyTrend($this->filters([
            'tab' => 'movement',
            'q' => 'VISIBLE',
        ]));

        $this->assertSame(['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04'], $trend['dates']);
        $this->assertSame([12, 0, 0, 0], $trend['qty_in']);
        $this->assertSame([0, 0, 5, 0], $trend['qty_out']);
    }

    public function test_item_search_matches_the_complete_sku_only(): void
    {
        $this->item('TRIP1');
        $this->item('TRIP11');
        $this->item('OTHER', ['name' => 'Barang TRIP1']);

        $rows = app(StockPeriodReport::class)->query($this->filters(['q' => 'TRIP1']))->get();

        $this->assertCount(1, $rows);
        $this->assertSame('TRIP1', $rows->first()->sku);
    }

    public function test_damaged_balances_are_separate_and_category_and_stock_filters_apply(): void
    {
        $item = $this->item('DAMAGED', ['safety_stock' => 10]);
        $this->mutation($item, 'in', 100, '2026-08-30 00:00:00');
        $this->mutation($item, 'in', 8, '2026-08-30 00:00:00', 'damaged_stock_mutations');
        $this->mutation($item, 'in', 3, '2026-09-01 00:00:00', 'damaged_stock_mutations');
        $this->mutation($item, 'out', 2, '2026-09-04 23:59:59', 'damaged_stock_mutations');
        $this->item('OTHER');
        $filters = $this->filters(['stock_type' => 'damaged', 'q' => 'DAMAGED', 'status' => 'positive', 'category_id' => '0']);
        $report = app(StockPeriodReport::class);
        $row = $report->query($filters)->first();
        $this->assertEquals([8, 3, 2, 9], [$row->opening, $row->qty_in, $row->qty_out, $row->closing]);
        $this->assertEquals(1, $report->summary($filters)->total_sku);
        $this->assertEquals(0, $report->query(array_merge($filters, ['status' => 'negative']))->count());
    }

    public function test_both_tabs_render_paginate_and_validate_dates(): void
    {
        $this->actingAs(User::factory()->create());
        for ($i = 0; $i < 11; $i++) {
            $this->item('SKU-'.str_pad($i, 2, '0', STR_PAD_LEFT));
        }
        foreach (['balance', 'movement'] as $tab) {
            $this->get(route('admin.reports.stock-as-of.index', $this->filters(['tab' => $tab, 'per_page' => 10, 'page' => 2])))
                ->assertOk()->assertSee('Saldo Stok')->assertSee('Pergerakan Stok')
                ->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && str_contains($rows->previousPageUrl(), 'tab='.$tab));
        }
        $this->get(route('admin.reports.stock-as-of.index', $this->filters(['tab' => 'movement'])))
            ->assertOk()
            ->assertSee('Tren Pergerakan Stok')
            ->assertSee('stock-movement-chart');
        $this->getJson(route('admin.reports.stock-as-of.data', $this->filters(['date_to' => '2026-08-01'])))
            ->assertUnprocessable();
        $this->getJson(route('admin.reports.stock-as-of.data', $this->filters(['date_from' => 'wrong'])))
            ->assertUnprocessable();
        $this->getJson(route('admin.reports.stock-as-of.data', $this->filters(['date_to' => '2028-01-01'])))
            ->assertUnprocessable();
    }

    public function test_export_matches_filtered_balances_and_movement_and_downloads(): void
    {
        $this->actingAs(User::factory()->create());
        $item = $this->item('EXPORT');
        $this->mutation($item, 'in', 10, '2026-08-30 00:00:00');
        $this->mutation($item, 'out', 2, '2026-09-01 00:00:00');
        $this->item('HIDDEN');
        foreach (['balance', 'movement'] as $tab) {
            $filters = $this->filters(['tab' => $tab, 'q' => 'EXPORT']);
            $export = new StockAsOfReportExport($filters);
            $this->assertCount(1, $export->collection());
            $mapped = $export->map($export->collection()->first());
            $this->assertSame('EXPORT', $mapped[0]);
            if ($tab === 'balance') {
                $this->assertSame([10, 0, 2, 8], array_slice($mapped, 5));
            } else {
                $this->assertSame('Fast Moving', $mapped[5]);
            }
            $this->get(route('admin.reports.stock-as-of.export', $filters))
                ->assertOk()->assertDownload('laporan-stok-'.$tab.'-2026-09-01-2026-09-04.xlsx');
        }
    }

    private function filters(array $overrides = []): array
    {
        return array_merge(['date_from' => '2026-09-01', 'date_to' => '2026-09-04', 'stock_type' => 'regular', 'tab' => 'balance'], $overrides);
    }

    private function item(string $sku, array $extra = []): Item
    {
        return Item::create(array_merge(['sku' => $sku, 'name' => 'Barang '.$sku, 'is_active' => true, 'is_bundle' => false, 'category_id' => 0], $extra));
    }

    private function mutation(Item $item, string $direction, int $qty, string $date, string $table = 'stock_mutations'): void
    {
        DB::table($table)->insert(['item_id' => $item->id, 'direction' => $direction, 'qty' => $qty, 'occurred_at' => $date, 'source_type' => 'test', 'source_id' => 1]);
    }
}
