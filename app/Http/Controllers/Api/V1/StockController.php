<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockApiSyncRecord;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $serverTime = now();
        $query = StockApiSyncRecord::query();
        if ($filters['updated_since']) {
            $query->where('source_updated_at', '>=', $filters['updated_since']);
        }
        $query->where('source_updated_at', '<=', $filters['updated_until'] ?? $serverTime);

        $total = (clone $query)->count();
        $records = $query->orderBy('source_updated_at')->orderBy('sku')
            ->forPage($filters['page'], $filters['per_page'])
            ->get();
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
            'data' => $records->map(fn (StockApiSyncRecord $record) => [
                'sku' => $record->sku,
                'name' => $record->name,
                'category' => $record->category,
                'uom' => $record->uom,
                'qty' => (float) $record->qty,
                'min_qty' => $record->min_qty === null ? null : (float) $record->min_qty,
                'status' => $record->status,
                'updated_at' => $record->source_updated_at->toIso8601String(),
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

        return [[
            'page' => $page,
            'per_page' => $perPage,
            'updated_since' => $since,
            'updated_until' => $until,
        ], null];
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

    private function error(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'INVALID_PARAMETER', 'message' => $message],
        ], 400);
    }
}
