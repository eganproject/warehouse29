<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InboundTransaction;
use App\Models\OutboundTransaction;
use App\Models\ReturnReason;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReturnReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.returns.index', [
            'inboundDataUrl' => route('admin.reports.returns.data', ['direction' => 'inbound']),
            'outboundDataUrl' => route('admin.reports.returns.data', ['direction' => 'outbound']),
            'defaultDateFrom' => now()->subDays(29)->toDateString(),
            'defaultDateTo' => now()->toDateString(),
            'returnReasons' => ReturnReason::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function data(Request $request, string $direction)
    {
        abort_unless(in_array($direction, ['inbound', 'outbound'], true), 404);

        [$dateFrom, $dateTo] = $this->dateRange($request);

        return $direction === 'inbound'
            ? $this->inboundData($request, $dateFrom, $dateTo)
            : $this->outboundData($request, $dateFrom, $dateTo);
    }

    private function inboundData(Request $request, Carbon $dateFrom, Carbon $dateTo)
    {
        $base = InboundTransaction::query()
            ->where('inbound_transactions.type', 'return')
            ->whereBetween('inbound_transactions.transacted_at', [$dateFrom, $dateTo]);

        $this->applyInboundFilters($base, $request);

        $recordsTotal = InboundTransaction::query()->where('type', 'return')->count();
        $recordsFiltered = (clone $base)->count();
        $reasonId = $request->filled('reason_id') ? (int) $request->input('reason_id') : null;

        $txStats = (clone $base)
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved")
            ->selectRaw("SUM(CASE WHEN status = 'finalized' THEN 1 ELSE 0 END) as finalized")
            ->selectRaw('SUM(CASE WHEN resi_id IS NOT NULL THEN 1 ELSE 0 END) as linked_resi')
            ->first();

        $items = $this->inboundItemsQuery($base, $reasonId);
        $itemStats = (clone $items)
            ->selectRaw('COUNT(*) as item_lines')
            ->selectRaw('COUNT(DISTINCT ri.item_id) as distinct_sku')
            ->selectRaw('COALESCE(SUM(ri.qty_resi), 0) as expected_qty')
            ->selectRaw('COALESCE(SUM(ri.qty_received), 0) as received_qty')
            ->selectRaw('COALESCE(SUM(ri.qty_difference), 0) as difference_qty')
            ->selectRaw('COALESCE(SUM(ri.qty_good), 0) as good_qty')
            ->selectRaw('COALESCE(SUM(ri.qty_damaged), 0) as damaged_qty')
            ->selectRaw("COALESCE(SUM(CASE WHEN rt.status = 'finalized' THEN ri.qty_good ELSE 0 END), 0) as stocked_good_qty")
            ->selectRaw("COALESCE(SUM(CASE WHEN rt.status = 'finalized' THEN ri.qty_damaged ELSE 0 END), 0) as stocked_damaged_qty")
            ->selectRaw("COALESCE(SUM(CASE WHEN rt.status = 'approved' THEN ri.qty_received ELSE 0 END), 0) as waiting_stock_qty")
            ->first();

        $summary = [
            'transactions' => (int) ($txStats->transactions ?? 0),
            'pending' => (int) ($txStats->pending ?? 0),
            'approved' => (int) ($txStats->approved ?? 0),
            'finalized' => (int) ($txStats->finalized ?? 0),
            'linked_resi' => (int) ($txStats->linked_resi ?? 0),
            'item_lines' => (int) ($itemStats->item_lines ?? 0),
            'distinct_sku' => (int) ($itemStats->distinct_sku ?? 0),
            'expected_qty' => (int) ($itemStats->expected_qty ?? 0),
            'received_qty' => (int) ($itemStats->received_qty ?? 0),
            'difference_qty' => (int) ($itemStats->difference_qty ?? 0),
            'good_qty' => (int) ($itemStats->good_qty ?? 0),
            'damaged_qty' => (int) ($itemStats->damaged_qty ?? 0),
            'stocked_good_qty' => (int) ($itemStats->stocked_good_qty ?? 0),
            'stocked_damaged_qty' => (int) ($itemStats->stocked_damaged_qty ?? 0),
            'waiting_stock_qty' => (int) ($itemStats->waiting_stock_qty ?? 0),
        ];
        $summary['received_rate'] = $this->percentage($summary['received_qty'], $summary['expected_qty']);
        $summary['good_rate'] = $this->percentage($summary['good_qty'], $summary['received_qty']);
        $summary['damaged_rate'] = $this->percentage($summary['damaged_qty'], $summary['received_qty']);
        $summary['finalization_rate'] = $this->percentage($summary['finalized'], $summary['transactions']);

        $analytics = [
            'daily' => $this->inboundDaily($items),
            'top_skus' => $this->inboundTopSkus($items),
            'reasons' => $this->inboundReasons($items),
            'submitters' => $this->topSubmitters($base, 'inbound_transactions'),
        ];

        [$start, $length] = $this->pagination($request);
        $rows = (clone $base)
            ->with(['items.item', 'items.returnReason', 'resi', 'creator', 'approver', 'finalizer'])
            ->orderByDesc('inbound_transactions.transacted_at')
            ->orderByDesc('inbound_transactions.id')
            ->skip($start)
            ->take($length)
            ->get()
            ->map(fn (InboundTransaction $transaction) => $this->mapInboundRow($transaction, $reasonId))
            ->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'period' => ['from' => $dateFrom->toDateString(), 'to' => $dateTo->toDateString()],
            'summary' => $summary,
            'analytics' => $analytics,
            'data' => $rows,
        ]);
    }

    private function outboundData(Request $request, Carbon $dateFrom, Carbon $dateTo)
    {
        $base = OutboundTransaction::query()
            ->where('outbound_transactions.type', 'return')
            ->whereBetween('outbound_transactions.transacted_at', [$dateFrom, $dateTo]);

        $this->applyOutboundFilters($base, $request);

        $recordsTotal = OutboundTransaction::query()->where('type', 'return')->count();
        $recordsFiltered = (clone $base)->count();
        $stockSource = in_array($request->input('stock_source'), ['regular', 'damaged'], true)
            ? $request->input('stock_source')
            : null;

        $txStats = (clone $base)
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved")
            ->first();

        $items = $this->outboundItemsQuery($base, $stockSource);
        $itemStats = (clone $items)
            ->selectRaw('COUNT(*) as item_lines')
            ->selectRaw('COUNT(DISTINCT oi.item_id) as distinct_sku')
            ->selectRaw('COALESCE(SUM(oi.qty), 0) as total_qty')
            ->selectRaw("COALESCE(SUM(CASE WHEN COALESCE(oi.stock_source, 'regular') = 'regular' THEN oi.qty ELSE 0 END), 0) as regular_qty")
            ->selectRaw("COALESCE(SUM(CASE WHEN oi.stock_source = 'damaged' THEN oi.qty ELSE 0 END), 0) as damaged_qty")
            ->selectRaw("COALESCE(SUM(CASE WHEN ot.status = 'approved' THEN oi.qty ELSE 0 END), 0) as issued_qty")
            ->selectRaw("COALESCE(SUM(CASE WHEN ot.status = 'pending' THEN oi.qty ELSE 0 END), 0) as waiting_approval_qty")
            ->first();

        $summary = [
            'transactions' => (int) ($txStats->transactions ?? 0),
            'pending' => (int) ($txStats->pending ?? 0),
            'approved' => (int) ($txStats->approved ?? 0),
            'item_lines' => (int) ($itemStats->item_lines ?? 0),
            'distinct_sku' => (int) ($itemStats->distinct_sku ?? 0),
            'total_qty' => (int) ($itemStats->total_qty ?? 0),
            'regular_qty' => (int) ($itemStats->regular_qty ?? 0),
            'damaged_qty' => (int) ($itemStats->damaged_qty ?? 0),
            'issued_qty' => (int) ($itemStats->issued_qty ?? 0),
            'waiting_approval_qty' => (int) ($itemStats->waiting_approval_qty ?? 0),
        ];
        $summary['approval_rate'] = $this->percentage($summary['approved'], $summary['transactions']);
        $summary['damaged_source_rate'] = $this->percentage($summary['damaged_qty'], $summary['total_qty']);

        $analytics = [
            'daily' => $this->outboundDaily($items),
            'top_skus' => $this->outboundTopSkus($items),
            'sources' => [
                ['label' => 'Stok reguler', 'value' => $summary['regular_qty']],
                ['label' => 'Stok rusak', 'value' => $summary['damaged_qty']],
            ],
            'submitters' => $this->topSubmitters($base, 'outbound_transactions'),
        ];

        [$start, $length] = $this->pagination($request);
        $rows = (clone $base)
            ->with(['items.item', 'creator', 'approver'])
            ->orderByDesc('outbound_transactions.transacted_at')
            ->orderByDesc('outbound_transactions.id')
            ->skip($start)
            ->take($length)
            ->get()
            ->map(fn (OutboundTransaction $transaction) => $this->mapOutboundRow($transaction, $stockSource))
            ->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'period' => ['from' => $dateFrom->toDateString(), 'to' => $dateTo->toDateString()],
            'summary' => $summary,
            'analytics' => $analytics,
            'data' => $rows,
        ]);
    }

    private function applyInboundFilters(EloquentBuilder $query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if (in_array($status, ['pending', 'approved', 'finalized'], true)) {
            $query->where('inbound_transactions.status', $status);
        }

        if ($request->filled('reason_id')) {
            $reasonId = (int) $request->input('reason_id');
            $query->whereHas('items', fn ($items) => $items->where('return_reason_id', $reasonId));
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($nested) use ($search) {
                $nested->where('inbound_transactions.code', 'like', "%{$search}%")
                    ->orWhere('inbound_transactions.ref_no', 'like', "%{$search}%")
                    ->orWhere('inbound_transactions.return_resi_no', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resi) use ($search) {
                        $resi->where('no_resi', 'like', "%{$search}%")
                            ->orWhere('id_pesanan', 'like', "%{$search}%");
                    })
                    ->orWhereHas('creator', fn ($user) => $user->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items.item', function ($item) use ($search) {
                        $item->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.returnReason', fn ($reason) => $reason->where('name', 'like', "%{$search}%"));
            });
        }
    }

    private function applyOutboundFilters(EloquentBuilder $query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if (in_array($status, ['pending', 'approved'], true)) {
            $query->where('outbound_transactions.status', $status);
        }

        $stockSource = (string) $request->input('stock_source', '');
        if (in_array($stockSource, ['regular', 'damaged'], true)) {
            $query->whereHas('items', function ($items) use ($stockSource) {
                $stockSource === 'regular'
                    ? $items->where(fn ($source) => $source->where('stock_source', 'regular')->orWhereNull('stock_source'))
                    : $items->where('stock_source', 'damaged');
            });
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($nested) use ($search) {
                $nested->where('outbound_transactions.code', 'like', "%{$search}%")
                    ->orWhere('outbound_transactions.ref_no', 'like', "%{$search}%")
                    ->orWhereHas('creator', fn ($user) => $user->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items.item', function ($item) use ($search) {
                        $item->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }
    }

    private function inboundItemsQuery(EloquentBuilder $base, ?int $reasonId): QueryBuilder
    {
        $transactions = (clone $base)->select([
            'inbound_transactions.id',
            'inbound_transactions.transacted_at',
            'inbound_transactions.status',
        ]);

        $query = DB::table('inbound_items as ri')
            ->joinSub($transactions, 'rt', 'rt.id', '=', 'ri.inbound_transaction_id');

        if ($reasonId) {
            $query->where('ri.return_reason_id', $reasonId);
        }

        return $query;
    }

    private function outboundItemsQuery(EloquentBuilder $base, ?string $stockSource): QueryBuilder
    {
        $transactions = (clone $base)->select([
            'outbound_transactions.id',
            'outbound_transactions.transacted_at',
            'outbound_transactions.status',
        ]);

        $query = DB::table('outbound_items as oi')
            ->joinSub($transactions, 'ot', 'ot.id', '=', 'oi.outbound_transaction_id');

        if ($stockSource === 'regular') {
            $query->where(fn ($source) => $source->where('oi.stock_source', 'regular')->orWhereNull('oi.stock_source'));
        } elseif ($stockSource === 'damaged') {
            $query->where('oi.stock_source', 'damaged');
        }

        return $query;
    }

    private function inboundDaily(QueryBuilder $items): array
    {
        return (clone $items)
            ->selectRaw('DATE(rt.transacted_at) as date')
            ->selectRaw('COUNT(DISTINCT rt.id) as transactions')
            ->selectRaw('COALESCE(SUM(ri.qty_resi), 0) as expected_qty')
            ->selectRaw('COALESCE(SUM(ri.qty_received), 0) as received_qty')
            ->selectRaw('COALESCE(SUM(ri.qty_difference), 0) as difference_qty')
            ->selectRaw('COALESCE(SUM(ri.qty_good), 0) as good_qty')
            ->selectRaw('COALESCE(SUM(ri.qty_damaged), 0) as damaged_qty')
            ->groupByRaw('DATE(rt.transacted_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'transactions' => (int) $row->transactions,
                'expected_qty' => (int) $row->expected_qty,
                'received_qty' => (int) $row->received_qty,
                'difference_qty' => (int) $row->difference_qty,
                'good_qty' => (int) $row->good_qty,
                'damaged_qty' => (int) $row->damaged_qty,
            ])->all();
    }

    private function outboundDaily(QueryBuilder $items): array
    {
        return (clone $items)
            ->selectRaw('DATE(ot.transacted_at) as date')
            ->selectRaw('COUNT(DISTINCT ot.id) as transactions')
            ->selectRaw('COALESCE(SUM(oi.qty), 0) as total_qty')
            ->selectRaw("COALESCE(SUM(CASE WHEN COALESCE(oi.stock_source, 'regular') = 'regular' THEN oi.qty ELSE 0 END), 0) as regular_qty")
            ->selectRaw("COALESCE(SUM(CASE WHEN oi.stock_source = 'damaged' THEN oi.qty ELSE 0 END), 0) as damaged_qty")
            ->groupByRaw('DATE(ot.transacted_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'transactions' => (int) $row->transactions,
                'total_qty' => (int) $row->total_qty,
                'regular_qty' => (int) $row->regular_qty,
                'damaged_qty' => (int) $row->damaged_qty,
            ])->all();
    }

    private function inboundTopSkus(QueryBuilder $items): array
    {
        return (clone $items)
            ->join('items as i', 'i.id', '=', 'ri.item_id')
            ->select(['i.sku', 'i.name'])
            ->selectRaw('COUNT(DISTINCT rt.id) as transactions')
            ->selectRaw('COALESCE(SUM(ri.qty_received), 0) as value')
            ->selectRaw('COALESCE(SUM(ri.qty_damaged), 0) as secondary')
            ->groupBy('i.id', 'i.sku', 'i.name')
            ->orderByDesc('value')
            ->limit(7)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->sku,
                'description' => $row->name,
                'value' => (int) $row->value,
                'secondary' => (int) $row->secondary,
                'transactions' => (int) $row->transactions,
            ])->all();
    }

    private function outboundTopSkus(QueryBuilder $items): array
    {
        return (clone $items)
            ->join('items as i', 'i.id', '=', 'oi.item_id')
            ->select(['i.sku', 'i.name'])
            ->selectRaw('COUNT(DISTINCT ot.id) as transactions')
            ->selectRaw('COALESCE(SUM(oi.qty), 0) as value')
            ->selectRaw("COALESCE(SUM(CASE WHEN oi.stock_source = 'damaged' THEN oi.qty ELSE 0 END), 0) as secondary")
            ->groupBy('i.id', 'i.sku', 'i.name')
            ->orderByDesc('value')
            ->limit(7)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->sku,
                'description' => $row->name,
                'value' => (int) $row->value,
                'secondary' => (int) $row->secondary,
                'transactions' => (int) $row->transactions,
            ])->all();
    }

    private function inboundReasons(QueryBuilder $items): array
    {
        return (clone $items)
            ->leftJoin('return_reasons as rr', 'rr.id', '=', 'ri.return_reason_id')
            ->selectRaw("COALESCE(rr.name, 'Belum ditentukan') as label")
            ->selectRaw('COALESCE(SUM(ri.qty_received), 0) as value')
            ->selectRaw('COUNT(DISTINCT rt.id) as transactions')
            ->groupBy('rr.id', 'rr.name')
            ->orderByDesc('value')
            ->limit(7)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'value' => (int) $row->value,
                'transactions' => (int) $row->transactions,
            ])->all();
    }

    private function topSubmitters(EloquentBuilder $base, string $table): array
    {
        return (clone $base)
            ->leftJoin('users as report_users', 'report_users.id', '=', $table.'.created_by')
            ->selectRaw("COALESCE(report_users.name, 'Tidak diketahui') as label")
            ->selectRaw('COUNT(*) as value')
            ->groupBy('report_users.id', 'report_users.name')
            ->orderByDesc('value')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->value])
            ->all();
    }

    private function mapInboundRow(InboundTransaction $transaction, ?int $reasonId): array
    {
        $allItems = $transaction->items ?? collect();
        $items = $reasonId
            ? $allItems->where('return_reason_id', $reasonId)->values()
            : $allItems;

        return [
            'id' => $transaction->id,
            'code' => $transaction->code,
            'transacted_at' => $transaction->transacted_at?->format('Y-m-d H:i'),
            'status' => $transaction->status ?: 'pending',
            'ref_no' => $transaction->ref_no ?: '-',
            'return_resi_no' => $transaction->return_resi_no ?: ($transaction->resi?->no_resi ?: '-'),
            'order_no' => $transaction->resi?->id_pesanan ?: '-',
            'submit_by' => $transaction->creator?->name ?: '-',
            'approved_by' => $transaction->approver?->name ?: '-',
            'finalized_by' => $transaction->finalizer?->name ?: '-',
            'note' => $transaction->note ?: '',
            'item_count' => $items->count(),
            'qty_expected' => (int) $items->sum('qty_resi'),
            'qty_received' => (int) $items->sum('qty_received'),
            'qty_difference' => (int) $items->sum('qty_difference'),
            'qty_good' => (int) $items->sum('qty_good'),
            'qty_damaged' => (int) $items->sum('qty_damaged'),
            'items' => $items->map(fn ($item) => [
                'sku' => $item->item?->sku ?: '-',
                'name' => $item->item?->name ?: '-',
                'qty_received' => (int) $item->qty_received,
                'qty_good' => (int) $item->qty_good,
                'qty_damaged' => (int) $item->qty_damaged,
                'reason' => $item->returnReason?->name ?: 'Belum ditentukan',
            ])->values(),
            'detail_url' => route('admin.inbound.returns.detail', $transaction->id),
        ];
    }

    private function mapOutboundRow(OutboundTransaction $transaction, ?string $stockSource): array
    {
        $allItems = $transaction->items ?? collect();
        $items = $stockSource
            ? $allItems->filter(fn ($item) => ($item->stock_source ?: 'regular') === $stockSource)->values()
            : $allItems;

        return [
            'id' => $transaction->id,
            'code' => $transaction->code,
            'transacted_at' => $transaction->transacted_at?->format('Y-m-d H:i'),
            'status' => $transaction->status ?: 'pending',
            'ref_no' => $transaction->ref_no ?: '-',
            'submit_by' => $transaction->creator?->name ?: '-',
            'approved_by' => $transaction->approver?->name ?: '-',
            'note' => $transaction->note ?: '',
            'item_count' => $items->count(),
            'total_qty' => (int) $items->sum('qty'),
            'regular_qty' => (int) $items->filter(fn ($item) => ($item->stock_source ?: 'regular') === 'regular')->sum('qty'),
            'damaged_qty' => (int) $items->where('stock_source', 'damaged')->sum('qty'),
            'items' => $items->map(fn ($item) => [
                'sku' => $item->item?->sku ?: '-',
                'name' => $item->item?->name ?: '-',
                'qty' => (int) $item->qty,
                'source' => $item->stock_source ?: 'regular',
            ])->values(),
            'detail_url' => route('admin.outbound.returns.detail', $transaction->id),
        ];
    }

    private function dateRange(Request $request): array
    {
        try {
            $from = $request->filled('date_from')
                ? Carbon::createFromFormat('Y-m-d', (string) $request->input('date_from'))->startOfDay()
                : now()->subDays(29)->startOfDay();
            $to = $request->filled('date_to')
                ? Carbon::createFromFormat('Y-m-d', (string) $request->input('date_to'))->endOfDay()
                : now()->endOfDay();
        } catch (\Throwable) {
            abort(422, 'Format tanggal tidak valid.');
        }

        abort_if($from->greaterThan($to), 422, 'Tanggal mulai tidak boleh melebihi tanggal akhir.');

        return [$from, $to];
    }

    private function pagination(Request $request): array
    {
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if (! in_array($length, [10, 25, 50, 100], true)) {
            $length = 10;
        }

        return [$start, $length];
    }

    private function percentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }
}
