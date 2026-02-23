<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use App\Models\Item;
use App\Models\StockMutation;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OutboundController extends Controller
{
    public function pickers()
    {
        return $this->index('picker', 'Outbound - Picker', 'pickers');
    }

    public function manuals()
    {
        return $this->index('manual', 'Outbound - Manual', 'manuals');
    }

    public function returns()
    {
        return $this->index('return', 'Outbound - Retur', 'returns');
    }

    public function pickersData(Request $request)
    {
        return $this->data($request, 'picker');
    }

    public function manualsData(Request $request)
    {
        return $this->data($request, 'manual');
    }

    public function returnsData(Request $request)
    {
        return $this->data($request, 'return');
    }

    public function pickersStore(Request $request)
    {
        return $this->store($request, 'picker');
    }

    public function manualsStore(Request $request)
    {
        return $this->store($request, 'manual');
    }

    public function returnsStore(Request $request)
    {
        return $this->store($request, 'return');
    }

    public function pickersShow(int $id)
    {
        return $this->show('picker', $id);
    }

    public function manualsShow(int $id)
    {
        return $this->show('manual', $id);
    }

    public function returnsShow(int $id)
    {
        return $this->show('return', $id);
    }

    public function pickersDetail(int $id)
    {
        return $this->detail('picker', 'Outbound - Picker', 'pickers', $id);
    }

    public function manualsDetail(int $id)
    {
        return $this->detail('manual', 'Outbound - Manual', 'manuals', $id);
    }

    public function returnsDetail(int $id)
    {
        return $this->detail('return', 'Outbound - Retur', 'returns', $id);
    }

    public function pickersUpdate(Request $request, int $id)
    {
        return $this->update($request, 'picker', $id);
    }

    public function manualsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'manual', $id);
    }

    public function returnsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'return', $id);
    }

    public function pickersDestroy(int $id)
    {
        return $this->destroy('picker', $id);
    }

    public function manualsDestroy(int $id)
    {
        return $this->destroy('manual', $id);
    }

    public function returnsDestroy(int $id)
    {
        return $this->destroy('return', $id);
    }

    private function index(string $type, string $pageTitle, string $routeBase)
    {
        $items = Item::orderBy('name')->get(['id', 'sku', 'name']);
        $baseOptions = $this->typeOptions();
        $typeOptions = ['all' => 'Semua'] + $baseOptions;
        $routeMap = [
            'picker' => [
                'store' => route('admin.outbound.pickers.store'),
                'show' => route('admin.outbound.pickers.show', ':id'),
                'update' => route('admin.outbound.pickers.update', ':id'),
                'delete' => route('admin.outbound.pickers.destroy', ':id'),
                'detail' => route('admin.outbound.pickers.detail', ':id'),
            ],
            'manual' => [
                'store' => route('admin.outbound.manuals.store'),
                'show' => route('admin.outbound.manuals.show', ':id'),
                'update' => route('admin.outbound.manuals.update', ':id'),
                'delete' => route('admin.outbound.manuals.destroy', ':id'),
                'detail' => route('admin.outbound.manuals.detail', ':id'),
            ],
            'return' => [
                'store' => route('admin.outbound.returns.store'),
                'show' => route('admin.outbound.returns.show', ':id'),
                'update' => route('admin.outbound.returns.update', ':id'),
                'delete' => route('admin.outbound.returns.destroy', ':id'),
                'detail' => route('admin.outbound.returns.detail', ':id'),
            ],
        ];

        return view('admin.stock-flow.index', [
            'pageTitle' => $pageTitle,
            'dataUrl' => route("admin.outbound.{$routeBase}.data"),
            'storeUrl' => route("admin.outbound.{$routeBase}.store"),
            'showUrlTpl' => route("admin.outbound.{$routeBase}.show", ':id'),
            'updateUrlTpl' => route("admin.outbound.{$routeBase}.update", ':id'),
            'deleteUrlTpl' => route("admin.outbound.{$routeBase}.destroy", ':id'),
            'detailUrlTpl' => route("admin.outbound.{$routeBase}.detail", ':id'),
            'items' => $items,
            'typeOptions' => $typeOptions,
            'typeDefault' => $type,
            'routeMap' => $routeMap,
        ]);
    }

    private function data(Request $request, string $type)
    {
        $allowed = array_keys($this->typeOptions());
        $filterType = $request->input('type');
        $baseType = null;
        if ($filterType === 'all') {
            $baseType = null;
        } elseif (in_array($filterType, $allowed, true)) {
            $baseType = $filterType;
        } else {
            $baseType = $type;
        }

        $query = OutboundTransaction::query()
            ->select([
                'outbound_transactions.id',
                'outbound_transactions.code',
                'outbound_transactions.transacted_at',
                'outbound_transactions.type',
                'outbound_transactions.ref_no',
                'outbound_transactions.note as tx_note',
                'items.sku',
                'items.name as item_name',
                'outbound_items.qty',
                'outbound_items.note as item_note',
            ])
            ->join('outbound_items', 'outbound_items.outbound_transaction_id', '=', 'outbound_transactions.id')
            ->join('items', 'items.id', '=', 'outbound_items.item_id')
            ->orderBy('outbound_transactions.transacted_at', 'desc');
        if ($baseType) {
            $query->where('outbound_transactions.type', $baseType);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('outbound_transactions.code', 'like', "%{$search}%")
                    ->orWhere('outbound_transactions.ref_no', 'like', "%{$search}%")
                    ->orWhere('items.sku', 'like', "%{$search}%")
                    ->orWhere('items.name', 'like', "%{$search}%");
            });
        }

        $this->applyDateFilter($query, $request);

        $recordsTotalQuery = OutboundItem::join('outbound_transactions', 'outbound_transactions.id', '=', 'outbound_items.outbound_transaction_id');
        if ($baseType) {
            $recordsTotalQuery->where('outbound_transactions.type', $baseType);
        }
        $recordsTotal = $recordsTotalQuery->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $itemLabel = trim(($row->sku ?? '').' - '.($row->item_name ?? ''));
            $ts = $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i') : '';
            $note = $row->item_note ?: ($row->tx_note ?? '');
            return [
                'id' => $row->id,
                'code' => $row->code,
                'transacted_at' => $ts,
                'item' => $itemLabel,
                'qty' => (int) $row->qty,
                'note' => $note,
                'type' => $row->type,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function show(string $type, int $id)
    {
        $tx = OutboundTransaction::with('items')
            ->where('type', $type)
            ->findOrFail($id);

        return response()->json([
            'id' => $tx->id,
            'code' => $tx->code,
            'ref_no' => $tx->ref_no,
            'note' => $tx->note,
            'transacted_at' => $tx->transacted_at?->format('Y-m-d\TH:i'),
            'items' => $tx->items->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'qty' => $item->qty,
                    'note' => $item->note ?? '',
                ];
            })->values(),
        ]);
    }

    private function detail(string $type, string $pageTitle, string $routeBase, int $id)
    {
        $tx = OutboundTransaction::with(['items.item'])
            ->where('type', $type)
            ->findOrFail($id);

        $totalQty = $tx->items->sum('qty');

        return view('admin.stock-flow.detail', [
            'pageTitle' => $pageTitle,
            'transaction' => $tx,
            'totalQty' => $totalQty,
            'backUrl' => route("admin.outbound.{$routeBase}.index"),
        ]);
    }

    private function store(Request $request, string $type)
    {
        $validated = $this->validatePayload($request);

        $prefix = match ($type) {
            'picker' => 'OUT-PCK',
            'return' => 'OUT-RET',
            default => 'OUT-MNL',
        };

        $code = $this->generateCode($prefix);
        $transactedAt = $validated['transacted_at'] ?? now();

        DB::beginTransaction();
        try {
            $tx = OutboundTransaction::create([
                'code' => $code,
                'type' => $type,
                'ref_no' => $validated['ref_no'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $row) {
                OutboundItem::create([
                    'outbound_transaction_id' => $tx->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);

                StockService::mutate([
                    'item_id' => $row['item_id'],
                    'direction' => 'out',
                    'qty' => $row['qty'],
                    'source_type' => 'outbound',
                    'source_subtype' => $type,
                    'source_id' => $tx->id,
                    'source_code' => $tx->code,
                    'note' => $row['note'] ?? null,
                    'occurred_at' => $transactedAt,
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan outbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Outbound berhasil disimpan',
        ]);
    }

    private function update(Request $request, string $type, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $tx = OutboundTransaction::where('type', $type)->findOrFail($id);

            StockService::rollbackBySource('outbound', $tx->id);
            StockMutation::where('source_type', 'outbound')->where('source_id', $tx->id)->delete();
            OutboundItem::where('outbound_transaction_id', $tx->id)->delete();

            $tx->update([
                'ref_no' => $validated['ref_no'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? $tx->transacted_at,
            ]);

            foreach ($validated['items'] as $row) {
                OutboundItem::create([
                    'outbound_transaction_id' => $tx->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);

                StockService::mutate([
                    'item_id' => $row['item_id'],
                    'direction' => 'out',
                    'qty' => $row['qty'],
                    'source_type' => 'outbound',
                    'source_subtype' => $type,
                    'source_id' => $tx->id,
                    'source_code' => $tx->code,
                    'note' => $row['note'] ?? null,
                    'occurred_at' => $validated['transacted_at'] ?? $tx->transacted_at,
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui outbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Outbound berhasil diperbarui',
        ]);
    }

    private function destroy(string $type, int $id)
    {
        DB::beginTransaction();
        try {
            $tx = OutboundTransaction::where('type', $type)->findOrFail($id);

            StockService::rollbackBySource('outbound', $tx->id);
            StockMutation::where('source_type', 'outbound')->where('source_id', $tx->id)->delete();
            $tx->delete();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return response()->json([
                'message' => $msg,
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus outbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Outbound berhasil dihapus',
        ]);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'ref_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['nullable', 'date'],
        ]);

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($row) => (int) ($row['qty'] ?? 0) > 0 && (int) ($row['item_id'] ?? 0) > 0)
            ->map(function ($row) {
                return [
                    'item_id' => (int) $row['item_id'],
                    'qty' => (int) $row['qty'],
                    'note' => $row['note'] ?? null,
                ];
            });

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Minimal 1 item diperlukan',
            ]);
        }

        $normalized = $items->groupBy('item_id')->map(function ($rows, $itemId) {
            $qty = $rows->sum('qty');
            $note = $rows->pluck('note')->first(fn ($n) => $n !== null && $n !== '') ?? null;
            return [
                'item_id' => (int) $itemId,
                'qty' => $qty,
                'note' => $note,
            ];
        })->values()->all();

        $validated['items'] = $normalized;
        if (!empty($validated['transacted_at'])) {
            $validated['transacted_at'] = Carbon::parse($validated['transacted_at']);
        } else {
            $validated['transacted_at'] = null;
        }

        return $validated;
    }

    private function typeOptions(): array
    {
        return [
            'picker' => 'Picker',
            'manual' => 'Manual',
            'return' => 'Retur',
        ];
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('outbound_transactions.transacted_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('outbound_transactions.transacted_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
