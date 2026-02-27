<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMutation;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        $items = Item::orderBy('name')->get(['id', 'sku', 'name']);

        return view('admin.inventory.stock-adjustments.index', [
            'items' => $items,
            'dataUrl' => route('admin.inventory.stock-adjustments.data'),
            'storeUrl' => route('admin.inventory.stock-adjustments.store'),
        ]);
    }

    public function data(Request $request)
    {
        $query = StockAdjustment::query()
            ->with(['items.item'])
            ->orderBy('transacted_at', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('stock_adjustments.code', 'like', "%{$search}%")
                    ->orWhere('stock_adjustments.note', 'like', "%{$search}%")
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = StockAdjustment::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $items = $row->items ?? collect();
            $labels = $items->map(function ($it) {
                $sku = trim($it->item?->sku ?? '');
                if ($sku === '') {
                    return '';
                }
                $qty = (int) ($it->qty ?? 0);
                $sign = $it->direction === 'in' ? '+' : '-';
                return sprintf('%s (%s%d)', $sku, $sign, $qty);
            })->filter()->values();
            $itemLabel = $labels->implode(', ');
            $ts = $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i') : '';
            $totalIn = (int) $items->where('direction', 'in')->sum('qty');
            $totalOut = (int) $items->where('direction', 'out')->sum('qty');
            return [
                'id' => $row->id,
                'code' => $row->code,
                'transacted_at' => $ts,
                'item' => $itemLabel ?: '-',
                'qty_in' => $totalIn,
                'qty_out' => $totalOut,
                'note' => $row->note ?? '',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $code = $this->generateCode('ADJ');
        $transactedAt = $validated['transacted_at'] ?? now();

        DB::beginTransaction();
        try {
            $adjustment = StockAdjustment::create([
                'code' => $code,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $row) {
                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'item_id' => $row['item_id'],
                    'direction' => $row['direction'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);

                StockService::mutate([
                    'item_id' => $row['item_id'],
                    'direction' => $row['direction'],
                    'qty' => $row['qty'],
                    'source_type' => 'adjustment',
                    'source_subtype' => 'manual',
                    'source_id' => $adjustment->id,
                    'source_code' => $adjustment->code,
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
                'message' => 'Gagal menyimpan penyesuaian stok',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Penyesuaian stok berhasil disimpan',
        ]);
    }

    public function show(int $id)
    {
        $adjustment = StockAdjustment::with('items')
            ->findOrFail($id);

        return response()->json([
            'id' => $adjustment->id,
            'code' => $adjustment->code,
            'note' => $adjustment->note,
            'transacted_at' => $adjustment->transacted_at?->format('Y-m-d H:i'),
            'items' => $adjustment->items->map(function ($row) {
                return [
                    'item_id' => $row->item_id,
                    'direction' => $row->direction,
                    'qty' => (int) $row->qty,
                    'note' => $row->note ?? '',
                ];
            })->values(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $adjustment = StockAdjustment::findOrFail($id);

            StockService::rollbackBySource('adjustment', $adjustment->id);
            StockMutation::where('source_type', 'adjustment')
                ->where('source_id', $adjustment->id)
                ->delete();
            StockAdjustmentItem::where('stock_adjustment_id', $adjustment->id)->delete();

            $adjustment->update([
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? now(),
            ]);

            foreach ($validated['items'] as $row) {
                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'item_id' => $row['item_id'],
                    'direction' => $row['direction'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);

                StockService::mutate([
                    'item_id' => $row['item_id'],
                    'direction' => $row['direction'],
                    'qty' => $row['qty'],
                    'source_type' => 'adjustment',
                    'source_subtype' => 'manual',
                    'source_id' => $adjustment->id,
                    'source_code' => $adjustment->code,
                    'note' => $row['note'] ?? null,
                    'occurred_at' => $validated['transacted_at'] ?? now(),
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
                'message' => 'Gagal memperbarui penyesuaian stok',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Penyesuaian stok berhasil diperbarui',
        ]);
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $adjustment = StockAdjustment::findOrFail($id);
            StockService::rollbackBySource('adjustment', $adjustment->id);
            StockMutation::where('source_type', 'adjustment')
                ->where('source_id', $adjustment->id)
                ->delete();
            StockAdjustmentItem::where('stock_adjustment_id', $adjustment->id)->delete();
            $adjustment->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus penyesuaian stok',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Penyesuaian stok berhasil dihapus',
        ]);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.direction' => ['required', 'string', Rule::in(['in', 'out'])],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
        ]);

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($row) => (int) ($row['qty'] ?? 0) > 0 && (int) ($row['item_id'] ?? 0) > 0)
            ->map(function ($row) {
                return [
                    'item_id' => (int) $row['item_id'],
                    'direction' => $row['direction'] === 'out' ? 'out' : 'in',
                    'qty' => (int) $row['qty'],
                    'note' => $row['note'] ?? null,
                ];
            })->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Minimal 1 item diperlukan',
            ]);
        }

        $duplicates = $items->groupBy('item_id')->filter(fn ($rows) => $rows->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item tidak boleh duplikat pada penyesuaian stok',
            ]);
        }

        $validated['items'] = $items->all();
        if (!empty($validated['transacted_at'])) {
            $validated['transacted_at'] = Carbon::parse($validated['transacted_at']);
        } else {
            $validated['transacted_at'] = null;
        }

        return $validated;
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
