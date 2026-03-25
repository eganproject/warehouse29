<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PickerSession;
use App\Models\PickerSessionItem;
use App\Models\PickerTransitItem;
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
            $deltaQty = (int) $validated['qty'];
            $occurredAt = now();
            $pickedDate = $session->started_at?->toDateString() ?? $occurredAt->toDateString();

            $itemRow = PickerSessionItem::where('picker_session_id', $session->id)
                ->where('item_id', $validated['item_id'])
                ->lockForUpdate()
                ->first();

            if ($itemRow) {
                $itemRow->qty += $deltaQty;
                if (!empty($validated['note'])) {
                    $itemRow->note = $validated['note'];
                }
                $itemRow->save();
            } else {
                PickerSessionItem::create([
                    'picker_session_id' => $session->id,
                    'item_id' => $validated['item_id'],
                    'qty' => $deltaQty,
                    'note' => $validated['note'] ?? null,
                ]);
            }

            $transitRow = PickerTransitItem::where('item_id', $validated['item_id'])
                ->where('picked_date', $pickedDate)
                ->lockForUpdate()
                ->first();

            if ($transitRow) {
                $transitRow->qty += $deltaQty;
                $transitRow->remaining_qty += $deltaQty;
                $transitRow->picked_at = $occurredAt;
                $transitRow->save();
            } else {
                PickerTransitItem::create([
                    'item_id' => $validated['item_id'],
                    'picked_date' => $pickedDate,
                    'qty' => $deltaQty,
                    'remaining_qty' => $deltaQty,
                    'picked_at' => $occurredAt,
                ]);
            }

            StockService::mutate([
                'item_id' => $validated['item_id'],
                'direction' => 'out',
                'qty' => $deltaQty,
                'source_type' => 'picker',
                'source_subtype' => 'mobile',
                'source_id' => $session->id,
                'source_code' => $session->code,
                'note' => $validated['note'] ?? null,
                'occurred_at' => $occurredAt,
                'created_by' => auth()->id(),
            ]);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
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

        DB::beginTransaction();
        try {
            $itemRow = PickerSessionItem::where('picker_session_id', $session->id)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            $newQty = (int) $validated['qty'];
            $oldQty = (int) $itemRow->qty;
            $delta = $newQty - $oldQty;
            $occurredAt = now();
            $pickedDate = $session->started_at?->toDateString() ?? $occurredAt->toDateString();

            if ($delta !== 0) {
                StockService::mutate([
                    'item_id' => $itemRow->item_id,
                    'direction' => $delta > 0 ? 'out' : 'in',
                    'qty' => abs($delta),
                    'source_type' => 'picker',
                    'source_subtype' => 'mobile',
                    'source_id' => $session->id,
                    'source_code' => $session->code,
                    'note' => $validated['note'] ?? $itemRow->note,
                    'occurred_at' => $occurredAt,
                    'created_by' => auth()->id(),
                ]);
            }

            $itemRow->qty = $newQty;
            $itemRow->note = $validated['note'] ?? $itemRow->note;
            $itemRow->save();

            $transitRow = PickerTransitItem::where('item_id', $itemRow->item_id)
                ->where('picked_date', $pickedDate)
                ->lockForUpdate()
                ->first();

            if ($transitRow) {
                $transitRow->qty += $delta;
                $transitRow->remaining_qty = max(0, $transitRow->remaining_qty + $delta);
                if ($delta !== 0) {
                    $transitRow->picked_at = $occurredAt;
                }
                if ($transitRow->qty <= 0) {
                    $transitRow->delete();
                } else {
                    $transitRow->save();
                }
            } elseif ($newQty > 0) {
                PickerTransitItem::create([
                    'item_id' => $itemRow->item_id,
                    'picked_date' => $pickedDate,
                    'qty' => $newQty,
                    'remaining_qty' => $newQty,
                    'picked_at' => $occurredAt,
                ]);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui item',
                'error' => $e->getMessage(),
            ], 500);
        }

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

        DB::beginTransaction();
        try {
            $itemRow = PickerSessionItem::where('picker_session_id', $session->id)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            $qty = (int) $itemRow->qty;
            $occurredAt = now();
            $pickedDate = $session->started_at?->toDateString() ?? $occurredAt->toDateString();

            $itemRow->delete();

            if ($qty > 0) {
                StockService::mutate([
                    'item_id' => $itemRow->item_id,
                    'direction' => 'in',
                    'qty' => $qty,
                    'source_type' => 'picker',
                    'source_subtype' => 'mobile',
                    'source_id' => $session->id,
                    'source_code' => $session->code,
                    'note' => $itemRow->note ?? null,
                    'occurred_at' => $occurredAt,
                    'created_by' => auth()->id(),
                ]);
            }

            $transitRow = PickerTransitItem::where('item_id', $itemRow->item_id)
                ->where('picked_date', $pickedDate)
                ->lockForUpdate()
                ->first();

            if ($transitRow) {
                $transitRow->qty -= $qty;
                $transitRow->remaining_qty = max(0, $transitRow->remaining_qty - $qty);
                $transitRow->picked_at = $occurredAt;
                if ($transitRow->qty <= 0) {
                    $transitRow->delete();
                } else {
                    $transitRow->save();
                }
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus item',
                'error' => $e->getMessage(),
            ], 500);
        }

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

            $occurredAt = now();

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
