<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PickerSession;
use App\Models\PickerSessionItem;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PickerSessionController extends Controller
{
    public function index()
    {
        $session = $this->currentDraftSession();

        return view('picker/session', [
            'session' => $session ? $this->serializeSession($session) : null,
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'start' => route('picker.start'),
                'current' => route('picker.current'),
                'itemsStore' => route('picker.items.store'),
                'itemsUpdate' => route('picker.items.update', ':id'),
                'itemsDestroy' => route('picker.items.destroy', ':id'),
                'submit' => route('picker.submit'),
                'searchItems' => route('picker.items.search'),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function current()
    {
        $session = $this->currentDraftSession();

        return response()->json([
            'session' => $session ? $this->serializeSession($session) : null,
        ]);
    }

    public function start()
    {
        $session = $this->currentDraftSession();
        if (!$session) {
            $session = $this->ensureDraftSession();
        }

        return response()->json([
            'session' => $this->serializeSession($session),
        ]);
    }

    public function storeItem(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);

        $session = $this->ensureDraftSession();

        DB::beginTransaction();
        try {
            $itemRow = PickerSessionItem::where('picker_session_id', $session->id)
                ->where('item_id', $validated['item_id'])
                ->lockForUpdate()
                ->first();

            if ($itemRow) {
                $itemRow->qty += (int) $validated['qty'];
                if (!empty($validated['note'])) {
                    $itemRow->note = $validated['note'];
                }
                $itemRow->save();
            } else {
                PickerSessionItem::create([
                    'picker_session_id' => $session->id,
                    'item_id' => $validated['item_id'],
                    'qty' => (int) $validated['qty'],
                    'note' => $validated['note'] ?? null,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan item',
                'error' => $e->getMessage(),
            ], 500);
        }

        $session->load('items.item');

        return response()->json([
            'session' => $this->serializeSession($session),
        ]);
    }

    public function updateItem(Request $request, int $id)
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);

        $session = $this->currentDraftSession();
        if (!$session) {
            throw ValidationException::withMessages([
                'session' => 'Sesi belum tersedia',
            ]);
        }

        $itemRow = PickerSessionItem::where('picker_session_id', $session->id)
            ->where('id', $id)
            ->firstOrFail();

        $itemRow->qty = (int) $validated['qty'];
        $itemRow->note = $validated['note'] ?? $itemRow->note;
        $itemRow->save();

        $session->load('items.item');

        return response()->json([
            'session' => $this->serializeSession($session),
        ]);
    }

    public function destroyItem(int $id)
    {
        $session = $this->currentDraftSession();
        if (!$session) {
            throw ValidationException::withMessages([
                'session' => 'Sesi belum tersedia',
            ]);
        }

        PickerSessionItem::where('picker_session_id', $session->id)
            ->where('id', $id)
            ->delete();

        $session->load('items.item');

        return response()->json([
            'session' => $this->serializeSession($session),
        ]);
    }

    public function submit()
    {
        DB::beginTransaction();
        try {
            $session = PickerSession::where('user_id', auth()->id())
                ->where('status', 'draft')
                ->lockForUpdate()
                ->latest('id')
                ->first();
            if (!$session) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi belum tersedia',
                ]);
            }

            $session->load('items.item');
            if ($session->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Minimal 1 item diperlukan',
                ]);
            }

            $insufficient = [];
            foreach ($session->items as $row) {
                $stock = ItemStock::where('item_id', $row->item_id)->lockForUpdate()->first();
                $available = (int) ($stock?->stock ?? 0);
                $required = (int) $row->qty;
                if ($available < $required) {
                    $insufficient[] = [
                        'item_id' => $row->item_id,
                        'sku' => $row->item?->sku ?? '',
                        'name' => $row->item?->name ?? '',
                        'available' => $available,
                        'required' => $required,
                    ];
                }
            }

            if (!empty($insufficient)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Stok tidak mencukupi',
                    'insufficient' => $insufficient,
                ], 422);
            }

            $occurredAt = now();
            foreach ($session->items as $row) {
                StockService::mutate([
                    'item_id' => $row->item_id,
                    'direction' => 'out',
                    'qty' => (int) $row->qty,
                    'source_type' => 'picker',
                    'source_subtype' => 'mobile',
                    'source_id' => $session->id,
                    'source_code' => $session->code,
                    'note' => $row->note ?? null,
                    'occurred_at' => $occurredAt,
                    'created_by' => auth()->id(),
                ]);
            }

            $session->status = 'submitted';
            $session->submitted_at = $occurredAt;
            $session->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyelesaikan sesi',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Penginputan selesai',
            'session' => $this->serializeSession($session->fresh('items.item')),
        ]);
    }

    public function searchItems(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $query = Item::query();
        if ($search !== '') {
            $query->where('sku', 'like', "%{$search}%");
        }

        $items = $query->orderBy('sku')
            ->get(['id', 'sku', 'name', 'address']);

        return response()->json([
            'items' => $items,
        ]);
    }

    private function currentDraftSession(): ?PickerSession
    {
        return PickerSession::with('items.item')
            ->where('user_id', auth()->id())
            ->where('status', 'draft')
            ->latest('id')
            ->first();
    }

    private function ensureDraftSession(): PickerSession
    {
        return DB::transaction(function () {
            $session = PickerSession::where('user_id', auth()->id())
                ->where('status', 'draft')
                ->lockForUpdate()
                ->latest('id')
                ->first();
            if ($session) {
                return $session;
            }

            return PickerSession::create([
                'code' => $this->generateCode('PKR'),
                'user_id' => auth()->id(),
                'status' => 'draft',
                'started_at' => now(),
            ]);
        });
    }

    private function serializeSession(PickerSession $session): array
    {
        return [
            'id' => $session->id,
            'code' => $session->code,
            'status' => $session->status,
            'started_at' => $session->started_at?->format('Y-m-d H:i'),
            'submitted_at' => $session->submitted_at?->format('Y-m-d H:i'),
            'items' => $session->items->map(function ($row) {
                return [
                    'id' => $row->id,
                    'item_id' => $row->item_id,
                    'sku' => $row->item?->sku ?? '',
                    'name' => $row->item?->name ?? '',
                    'address' => $row->item?->address ?? '',
                    'qty' => (int) $row->qty,
                    'note' => $row->note,
                ];
            })->values(),
        ];
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.Carbon::now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
