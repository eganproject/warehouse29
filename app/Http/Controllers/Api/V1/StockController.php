<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockApiSyncRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$filters, $error] = $this->filters($request);
        if ($error) {
            return $error;
        }

        // A stable upper bound prevents rows created while a multi-page sync is
        // running from shifting subsequent pages. The caller must reuse the
        // returned server_time as updated_until for page 2 and onward.
        $serverTime = now('Asia/Jakarta');

        if ($filters['as_of']) {
            $query = $this->historicalQuery($filters['as_of']);
        } else {
            $query = StockApiSyncRecord::query();
            if ($filters['updated_since']) {
                $query->where('source_updated_at', '>=', $filters['updated_since']);
            }
            $query->where('source_updated_at', '<=', $filters['updated_until'] ?? $serverTime);
        }

        $total = (clone $query)->count();

        if ($filters['as_of']) {
            $records = $query->orderBy('sr.sku')
                ->forPage($filters['page'], $filters['per_page'])
                ->get();
        } else {
            $records = $query->orderBy('source_updated_at')->orderBy('sku')
                ->forPage($filters['page'], $filters['per_page'])
                ->get();
        }

        return response()->json([
            'success' => true,
            'meta' => [
                'warehouse_code' => config('stock_api.warehouse_code'),
                'server_time' => $serverTime->toIso8601String(),
                'page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total' => $total,
                'total_pages' => (int) ceil($total / $filters['per_page']),
            ],
            'data' => $records->map(fn (object $record) => [
                'sku' => $record->sku,
                'name' => $record->name,
                'category' => $record->category,
                'uom' => $record->uom,
                'qty' => (float) ($record->historical_qty ?? $record->qty),
                'min_qty' => $record->min_qty === null ? null : (float) $record->min_qty,
                'status' => $record->status,
                'updated_at' => $this->isoTimestamp($record->historical_updated_at ?? $record->source_updated_at),
            ])->values(),
        ]);
    }

    private function filters(Request $request): array
    {
        $page = filter_var($request->input('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $perPage = filter_var($request->input('per_page', 100), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
        if ($page === false || $perPage === false) {
            return [[], $this->error('page dan per_page harus berupa integer positif; per_page maksimum 500.')];
        }

        $since = $this->parseIsoTimestamp($request->input('updated_since'));
        $until = $this->parseIsoTimestamp($request->input('updated_until'));
        if ($since === false || $until === false) {
            return [[], $this->error('updated_since dan updated_until harus ISO-8601 dengan offset zona waktu.')];
        }
        if ($since && $until && $since->gt($until)) {
            return [[], $this->error('updated_since tidak boleh melebihi updated_until.')];
        }

        $asOf = $this->parseAsOfDate($request->input('as_of'));
        if ($asOf === false) {
            return [[], $this->error('as_of harus berupa tanggal YYYY-MM-DD yang valid (WIB).')];
        }
        if ($asOf && ($since || $until)) {
            return [[], $this->error('as_of tidak dapat dipakai bersama updated_since atau updated_until.')];
        }

        return [[
            'page' => $page,
            'per_page' => $perPage,
            'updated_since' => $since,
            'updated_until' => $until,
            'as_of' => $asOf,
        ], null];
    }

    /**
     * Build the historical balance from the same mutation ledger used by the
     * stock-as-of report. Sync records remain the source of the API fields and
     * keep deleted SKUs visible with a zero balance.
     */
    private function historicalQuery(CarbonImmutable $asOf): Builder
    {
        $regularAgg = DB::table('stock_mutations')
            ->select('item_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END), 0) as stock_as_of")
            ->selectRaw('MAX(occurred_at) as last_mutation_at')
            ->where('occurred_at', '<=', $asOf)
            ->groupBy('item_id');

        $bundleAgg = DB::table('item_bundles as ib')
            ->leftJoinSub($regularAgg, 'component_stock', 'component_stock.item_id', '=', 'ib.component_item_id')
            ->select('ib.bundle_item_id')
            ->selectRaw('COALESCE(MIN(FLOOR(COALESCE(component_stock.stock_as_of, 0) / NULLIF(ib.qty, 0))), 0) as stock_as_of')
            ->selectRaw('MAX(component_stock.last_mutation_at) as last_mutation_at')
            ->groupBy('ib.bundle_item_id');

        return DB::table('stock_api_sync_records as sr')
            ->leftJoin('items as i', 'i.id', '=', 'sr.item_id')
            ->leftJoinSub($regularAgg, 'regular_stock', 'regular_stock.item_id', '=', 'sr.item_id')
            ->leftJoinSub($bundleAgg, 'bundle_stock', 'bundle_stock.bundle_item_id', '=', 'sr.item_id')
            ->select([
                'sr.sku',
                'sr.name',
                'sr.category',
                'sr.uom',
                'sr.min_qty',
                'sr.status',
                'sr.source_updated_at',
                DB::raw('CASE WHEN COALESCE(i.is_bundle, 0) = 1 THEN COALESCE(bundle_stock.stock_as_of, 0) ELSE COALESCE(regular_stock.stock_as_of, 0) END as historical_qty'),
                DB::raw('CASE WHEN COALESCE(i.is_bundle, 0) = 1 THEN COALESCE(bundle_stock.last_mutation_at, sr.source_updated_at) ELSE COALESCE(regular_stock.last_mutation_at, sr.source_updated_at) END as historical_updated_at'),
            ]);
    }

    private function parseIsoTimestamp(mixed $value): CarbonImmutable|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || ! preg_match('/T.*(?:Z|[+-]\\d{2}:\\d{2})$/', $value)) {
            return false;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return false;
        }
    }

    private function parseAsOfDate(mixed $value): CarbonImmutable|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Jakarta');

            return $date->format('Y-m-d') === $value ? $date->endOfDay() : false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isoTimestamp(mixed $timestamp): string
    {
        return ($timestamp instanceof \DateTimeInterface
            ? CarbonImmutable::instance($timestamp)
            : CarbonImmutable::parse($timestamp))
            ->setTimezone('Asia/Jakarta')
            ->toIso8601String();
    }

    private function error(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'INVALID_PARAMETER', 'message' => $message],
        ], 400);
    }
}
