<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\Item;
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
            ->select([
                'damaged_goods.id',
                'damaged_goods.code',
                'damaged_goods.source_type',
                'damaged_goods.source_ref',
                'damaged_goods.transacted_at',
                'damaged_goods.note as damage_note',
                'items.sku',
                'items.name as item_name',
                'damaged_good_items.qty',
                'damaged_good_items.note as item_note',
            ])
            ->join('damaged_good_items', 'damaged_good_items.damaged_good_id', '=', 'damaged_goods.id')
            ->join('items', 'items.id', '=', 'damaged_good_items.item_id')
            ->orderBy('damaged_goods.transacted_at', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('damaged_goods.code', 'like', "%{$search}%")
                    ->orWhere('items.sku', 'like', "%{$search}%")
                    ->orWhere('items.name', 'like', "%{$search}%");
            });
        }

        $recordsTotal = DamagedGoodItem::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $sourceLabels = $this->sourceLabels();
        $data = $query->get()->map(function ($row) use ($sourceLabels) {
            $itemLabel = trim(($row->sku ?? '').' - '.($row->item_name ?? ''));
            $ts = $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i') : '';
            $note = $row->item_note ?: ($row->damage_note ?? '');
            return [
                'id' => $row->id,
                'code' => $row->code,
                'source' => $sourceLabels[$row->source_type] ?? $row->source_type,
                'source_ref' => $row->source_ref ?? '',
                'transacted_at' => $ts,
                'item' => $itemLabel,
                'qty' => (int) $row->qty,
                'note' => $note,
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
