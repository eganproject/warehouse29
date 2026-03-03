<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\Item;
use App\Models\StockMutation;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DamagedGoodsController extends Controller
{
    public function index()
    {
        $items = Item::orderBy('name')->get(['id', 'sku', 'name']);

        return view('admin.inventory.damaged-goods.index', [
            'items' => $items,
            'dataUrl' => route('admin.inventory.damaged-goods.data'),
            'storeUrl' => route('admin.inventory.damaged-goods.store'),
        ]);
    }

    public function data(Request $request)
    {
        $query = DamagedGood::query()
            ->with(['items.item', 'creator'])
            ->orderBy('transacted_at', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('damaged_goods.code', 'like', "%{$search}%")
                    ->orWhere('damaged_goods.source_ref', 'like', "%{$search}%")
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = DamagedGood::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $sourceLabels = $this->sourceLabels();
        $data = $query->get()->map(function ($row) use ($sourceLabels) {
            $items = $row->items ?? collect();
            $labels = $items->map(function ($it) {
                $sku = trim($it->item?->sku ?? '');
                if ($sku === '') {
                    return '';
                }
                $qty = (int) ($it->qty ?? 0);
                return sprintf('%s (%d)', $sku, $qty);
            })->filter()->values();
            $itemLabel = $labels->implode(', ');
            $ts = $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i') : '';
            $note = $row->note ?? '';
            $totalQty = (int) $items->sum('qty');
            return [
                'id' => $row->id,
                'code' => $row->code,
                'source' => $sourceLabels[$row->source_type] ?? $row->source_type,
                'source_ref' => $row->source_ref ?? '',
                'transacted_at' => $ts,
                'submit_by' => $row->creator?->name ?? '-',
                'item' => $itemLabel ?: '-',
                'qty' => $totalQty,
                'note' => $note,
                'status' => $row->status ?? 'pending',
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

        $code = $this->generateCode('DMG');
        $transactedAt = $validated['transacted_at'] ?? now();

        DB::beginTransaction();
        try {
            $damage = DamagedGood::create([
                'code' => $code,
                'source_type' => $validated['source_type'],
                'source_ref' => $validated['source_ref'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $row) {
                DamagedGoodItem::create([
                    'damaged_good_id' => $damage->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);

                if ($validated['source_type'] === 'display') {
                    StockService::mutate([
                        'item_id' => $row['item_id'],
                        'direction' => 'out',
                        'qty' => $row['qty'],
                        'source_type' => 'damaged',
                        'source_subtype' => $validated['source_type'],
                        'source_id' => $damage->id,
                        'source_code' => $damage->code,
                        'note' => $row['note'] ?? null,
                        'occurred_at' => $transactedAt,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Barang rusak berhasil disimpan',
        ]);
    }

    public function show(int $id)
    {
        $damage = DamagedGood::with('items')
            ->findOrFail($id);

        return response()->json([
            'id' => $damage->id,
            'code' => $damage->code,
            'source_type' => $damage->source_type,
            'source_ref' => $damage->source_ref,
            'note' => $damage->note,
            'status' => $damage->status ?? 'pending',
            'transacted_at' => $damage->transacted_at?->format('Y-m-d H:i'),
            'items' => $damage->items->map(function ($row) {
                return [
                    'item_id' => $row->item_id,
                    'qty' => (int) $row->qty,
                    'note' => $row->note,
                ];
            })->values(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $damage = DamagedGood::findOrFail($id);
            if (($damage->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa diubah'], 422);
            }

            StockService::rollbackBySource('damaged', $damage->id);
            StockMutation::where('source_type', 'damaged')
                ->where('source_id', $damage->id)
                ->delete();
            DamagedGoodItem::where('damaged_good_id', $damage->id)->delete();

            $damage->update([
                'source_type' => $validated['source_type'],
                'source_ref' => $validated['source_ref'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? now(),
            ]);

            foreach ($validated['items'] as $row) {
                DamagedGoodItem::create([
                    'damaged_good_id' => $damage->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);

                if ($validated['source_type'] === 'display') {
                    StockService::mutate([
                        'item_id' => $row['item_id'],
                        'direction' => 'out',
                        'qty' => $row['qty'],
                        'source_type' => 'damaged',
                        'source_subtype' => $validated['source_type'],
                        'source_id' => $damage->id,
                        'source_code' => $damage->code,
                        'note' => $row['note'] ?? null,
                        'occurred_at' => $validated['transacted_at'] ?? now(),
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Barang rusak berhasil diperbarui',
        ]);
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $damage = DamagedGood::findOrFail($id);
            if (($damage->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa dihapus'], 422);
            }
            StockService::rollbackBySource('damaged', $damage->id);
            StockMutation::where('source_type', 'damaged')
                ->where('source_id', $damage->id)
                ->delete();
            DamagedGoodItem::where('damaged_good_id', $damage->id)->delete();
            $damage->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Barang rusak berhasil dihapus',
        ]);
    }

    public function approve(int $id)
    {
        $damage = DamagedGood::findOrFail($id);
        if (($damage->status ?? 'pending') === 'approved') {
            return response()->json(['message' => 'Data sudah disetujui']);
        }
        $damage->status = 'approved';
        $damage->approved_at = now();
        $damage->approved_by = auth()->id();
        $damage->save();

        return response()->json(['message' => 'Barang rusak berhasil disetujui']);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'source_type' => ['required', 'string', Rule::in(['display', 'inbound_return'])],
            'source_ref' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
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
                'items' => 'Item tidak boleh duplikat pada barang rusak',
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

    private function sourceLabels(): array
    {
        return [
            'display' => 'Display',
            'inbound_return' => 'Retur Inbound',
        ];
    }
}
