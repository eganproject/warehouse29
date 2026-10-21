<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\StockPeriodReport;
use App\Support\TokoFilter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Data laporan "Stok Per Tanggal → Pergerakan Stok" dalam bentuk JSON.
 * Memakai StockPeriodReport yang sama dengan halaman & export agar angkanya selalu identik.
 */
class StockMovementController extends Controller
{
    private const INCLUDES = ['trend', 'per_toko'];

    private const PARAMS = ['date_from', 'date_to', 'stock_type', 'category_id', 'sku', 'status', 'movement', 'toko_id', 'include', 'page', 'per_page'];

    public function index(Request $request, StockPeriodReport $report): JsonResponse
    {
        [$filters, $includes, $page, $perPage, $error] = $this->filters($request);
        if ($error) {
            return $error;
        }

        $total = $report->query($filters)->count();
        $summary = $report->summary($filters);
        $rows = $report->ordered($filters)->forPage($page, $perPage)->get();
        $tokoId = StockPeriodReport::tokoId($filters);

        $response = [
            'success' => true,
            'meta' => [
                'warehouse_code' => config('stock_api.warehouse_code'),
                'server_time' => now('Asia/Jakarta')->toIso8601String(),
                'filters' => [
                    'date_from' => $filters['date_from'],
                    'date_to' => $filters['date_to'],
                    'days' => $report->days($filters),
                    'stock_type' => $filters['stock_type'],
                    'category_id' => $filters['category_id'] === '' ? null : (int) $filters['category_id'],
                    'sku' => $filters['q'] === '' ? null : $filters['q'],
                    'status' => $filters['status'] ?: null,
                    'movement' => $filters['movement'] ?: null,
                    'toko_id' => $tokoId,
                    'toko' => TokoFilter::tokoLabel($tokoId),
                ],
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
            'summary' => [
                'total_sku' => (int) $summary->total_sku,
                'opening' => (float) $summary->opening,
                'qty_in' => (float) $summary->qty_in,
                'qty_out' => (float) $summary->qty_out,
                'closing' => (float) $summary->closing,
                'movement_count' => [
                    'fast' => (int) $summary->fast,
                    'medium' => (int) $summary->medium,
                    'slow' => (int) $summary->slow,
                    'non' => (int) $summary->non,
                ],
            ],
            'data' => $rows->map(fn (object $row) => [
                'sku' => $row->sku,
                'name' => $row->name,
                'category' => $row->category,
                'uom' => $row->uom ?: null,
                'address' => $row->address ?: null,
                'safety_stock' => (float) $row->safety_stock,
                'opening' => (float) $row->opening,
                'qty_in' => (float) $row->qty_in,
                'qty_out' => (float) $row->qty_out,
                'closing' => (float) $row->closing,
                'outgoing_days' => (int) $row->outgoing_days,
                'outgoing_frequency' => (int) $row->outgoing_frequency,
                'average_out_per_day' => round((float) $row->average_out, 4),
                'days_cover' => $row->days_cover === null ? null : round((float) $row->days_cover, 2),
                'contribution_percent' => round((float) $row->contribution_percent, 4),
                'cumulative_percent' => round((float) $row->cumulative_percent, 4),
                'movement' => $row->movement,
                'movement_label' => StockPeriodReport::MOVEMENTS[$row->movement] ?? $row->movement,
                'last_out_at' => $row->last_out_at
                    ? CarbonImmutable::parse($row->last_out_at, 'Asia/Jakarta')->toIso8601String()
                    : null,
            ])->values(),
        ];

        if (in_array('trend', $includes, true)) {
            $trend = $report->dailyTrend($filters);
            $response['daily_trend'] = collect($trend['dates'])->map(fn (string $date, int $i) => [
                'date' => $date,
                'qty_in' => $trend['qty_in'][$i],
                'qty_out' => $trend['qty_out'][$i],
            ])->values();
        }

        if (in_array('per_toko', $includes, true)) {
            $response['per_toko'] = $report->outgoingPerToko($filters)->map(fn (object $row) => [
                // -1 = mutasi bukan dari resi, 0 = resi tanpa toko.
                'toko_id' => (int) $row->toko_key > 0 ? (int) $row->toko_key : null,
                'toko' => $row->toko,
                'source' => match (true) {
                    (int) $row->toko_key === -1 => 'non_resi',
                    (int) $row->toko_key === 0 => 'resi_without_toko',
                    default => 'toko',
                },
                'qty_out' => (float) $row->qty_out,
                'frequency' => (int) $row->frequency,
                'sku_count' => (int) $row->sku_count,
                'resi_count' => (int) $row->resi_count,
                'active_days' => (int) $row->active_days,
            ])->values();
        }

        return response()->json($response);
    }

    /**
     * Parameter mengikuti filter halaman laporan (tab Pergerakan), dengan respons error format API.
     */
    private function filters(Request $request): array
    {
        $validator = validator($request->query(), [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'stock_type' => ['nullable', 'in:regular,damaged'],
            'category_id' => ['nullable', 'integer', 'min:0'],
            'sku' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:positive,zero,negative,low'],
            'movement' => ['nullable', 'in:fast,medium,slow,non'],
            'toko_id' => ['nullable', 'regex:/^(all|none|[0-9]+)$/'],
            'include' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ], [
            'date_format' => ':attribute harus berformat YYYY-MM-DD.',
            'in' => ':attribute tidak valid.',
            'integer' => ':attribute harus berupa angka bulat.',
            'min' => ':attribute minimal :min.',
            'max' => ':attribute maksimal :max.',
            'regex' => ':attribute harus berupa id toko, "none", atau "all".',
            'string' => ':attribute harus berupa teks.',
        ], array_combine(self::PARAMS, self::PARAMS));
        if ($validator->fails()) {
            return [[], [], 0, 0, $this->error($validator->errors()->first())];
        }
        $values = $validator->validated();

        $includes = array_values(array_filter(array_map('trim', explode(',', (string) ($values['include'] ?? '')))));
        if ($unknown = array_diff($includes, self::INCLUDES)) {
            return [[], [], 0, 0, $this->error('include hanya menerima: '.implode(', ', self::INCLUDES).'. Tidak dikenal: '.implode(', ', $unknown).'.')];
        }

        $dateTo = $values['date_to'] ?? now('Asia/Jakarta')->toDateString();
        $dateFrom = $values['date_from'] ?? Carbon::parse($dateTo)->startOfMonth()->toDateString();
        if ($dateTo < $dateFrom) {
            return [[], [], 0, 0, $this->error('date_to harus sama dengan atau setelah date_from.')];
        }
        if ($dateTo > Carbon::parse($dateFrom)->addDays(365)->toDateString()) {
            return [[], [], 0, 0, $this->error('Rentang tanggal maksimal 366 hari.')];
        }

        $filters = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'tab' => 'movement',
            'stock_type' => $values['stock_type'] ?? 'regular',
            'category_id' => isset($values['category_id']) ? (string) $values['category_id'] : '',
            'q' => trim($values['sku'] ?? ''),
            'status' => $values['status'] ?? '',
            'movement' => $values['movement'] ?? '',
            'toko_id' => (string) ($values['toko_id'] ?? ''),
        ];

        return [$filters, $includes, (int) ($values['page'] ?? 1), (int) ($values['per_page'] ?? 100), null];
    }

    private function error(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'INVALID_PARAMETER', 'message' => $message],
        ], 400);
    }
}
