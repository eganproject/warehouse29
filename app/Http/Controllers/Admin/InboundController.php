<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\StockMutation;
use App\Imports\InboundReceiptsImport;
use App\Imports\InboundReturnsImport;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class InboundController extends Controller
{
    public function receipts()
    {
        return $this->index('receipt', 'Inbound - Penerimaan Barang', 'receipts');
    }

    public function returns()
    {
        return $this->index('return', 'Inbound - Retur', 'returns');
    }

    public function manuals()
    {
        return $this->index('manual', 'Inbound - Manual', 'manuals');
    }

    public function receiptsData(Request $request)
    {
        return $this->data($request, 'receipt');
    }

    public function returnsData(Request $request)
    {
        return $this->data($request, 'return');
    }

    public function manualsData(Request $request)
    {
        return $this->data($request, 'manual');
    }

    public function receiptsStore(Request $request)
    {
        return $this->store($request, 'receipt');
    }

    public function returnsStore(Request $request)
    {
        return $this->store($request, 'return');
    }

    public function manualsStore(Request $request)
    {
        return $this->store($request, 'manual');
    }

    public function receiptsShow(int $id)
    {
        return $this->show('receipt', $id);
    }

    public function returnsShow(int $id)
    {
        return $this->show('return', $id);
    }

    public function manualsShow(int $id)
    {
        return $this->show('manual', $id);
    }

    public function receiptsDetail(int $id)
    {
        return $this->detail('receipt', 'Inbound - Penerimaan Barang', 'receipts', $id);
    }

    public function returnsDetail(int $id)
    {
        return $this->detail('return', 'Inbound - Retur', 'returns', $id);
    }

    public function manualsDetail(int $id)
    {
        return $this->detail('manual', 'Inbound - Manual', 'manuals', $id);
    }

    public function receiptsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'receipt', $id);
    }

    public function returnsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'return', $id);
    }

    public function manualsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'manual', $id);
    }

    public function receiptsDestroy(int $id)
    {
        return $this->destroy('receipt', $id);
    }

    public function returnsDestroy(int $id)
    {
        return $this->destroy('return', $id);
    }

    public function manualsDestroy(int $id)
    {
        return $this->destroy('manual', $id);
    }

    public function receiptsApprove(int $id)
    {
        return $this->approve('receipt', $id);
    }

    public function returnsApprove(int $id)
    {
        return $this->approve('return', $id);
    }

    public function manualsApprove(int $id)
    {
        return $this->approve('manual', $id);
    }

    public function manualsImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new InboundReceiptsImport();
        DB::beginTransaction();
        try {
            Excel::import($import, $request->file('file'));
            $groups = $import->groups ?? [];
            if (empty($groups)) {
                throw ValidationException::withMessages([
                    'file' => 'Tidak ada data valid untuk diimport',
                ]);
            }

            $createdTx = 0;
            $createdItems = 0;
            foreach ($groups as $group) {
                $transactedAt = now();
                if (!empty($group['transacted_at'])) {
                    try {
                        $transactedAt = Carbon::parse($group['transacted_at']);
                    } catch (\Throwable $e) {
                        throw ValidationException::withMessages([
                            'file' => 'Format transacted_at tidak valid: '.$group['transacted_at'],
                        ]);
                    }
                }

                $tx = InboundTransaction::create([
                    'code' => $this->generateCode('INB-MNL'),
                    'type' => 'manual',
                    'ref_no' => $group['ref_no'] ?? null,
                    'note' => $group['note'] ?? null,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'pending',
                ]);
                $createdTx++;

                foreach ($group['items'] as $row) {
                    InboundItem::create([
                        'inbound_transaction_id' => $tx->id,
                        'item_id' => $row['item_id'],
                        'qty' => $row['qty'],
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;

                    StockService::mutate([
                        'item_id' => $row['item_id'],
                        'direction' => 'in',
                        'qty' => $row['qty'],
                        'source_type' => 'inbound',
                        'source_subtype' => 'manual',
                        'source_id' => $tx->id,
                        'source_code' => $tx->code,
                        'note' => $row['note'] ?? null,
                        'occurred_at' => $transactedAt,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import inbound manual berhasil',
                'transactions' => $createdTx,
                'items' => $createdItems,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import inbound manual',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function returnsImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new InboundReturnsImport();
        DB::beginTransaction();
        try {
            Excel::import($import, $request->file('file'));
            $groups = $import->groups ?? [];
            if (empty($groups)) {
                throw ValidationException::withMessages([
                    'file' => 'Tidak ada data valid untuk diimport',
                ]);
            }

            $createdTx = 0;
            $createdItems = 0;
            foreach ($groups as $group) {
                $transactedAt = now();
                if (!empty($group['transacted_at'])) {
                    try {
                        $transactedAt = Carbon::parse($group['transacted_at']);
                    } catch (\Throwable $e) {
                        throw ValidationException::withMessages([
                            'file' => 'Format transacted_at tidak valid: '.$group['transacted_at'],
                        ]);
                    }
                }
                $tx = InboundTransaction::create([
                    'code' => $this->generateCode('INB-RET'),
                    'type' => 'return',
                    'ref_no' => $group['ref_no'] ?? null,
                    'note' => $group['note'] ?? null,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'pending',
                ]);
                $createdTx++;

                foreach ($group['items'] as $row) {
                    InboundItem::create([
                        'inbound_transaction_id' => $tx->id,
                        'item_id' => $row['item_id'],
                        'qty' => $row['qty'],
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;

                    StockService::mutate([
                        'item_id' => $row['item_id'],
                        'direction' => 'in',
                        'qty' => $row['qty'],
                        'source_type' => 'inbound',
                        'source_subtype' => 'return',
                        'source_id' => $tx->id,
                        'source_code' => $tx->code,
                        'note' => $row['note'] ?? null,
                        'occurred_at' => $transactedAt,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import retur inbound berhasil',
                'transactions' => $createdTx,
                'items' => $createdItems,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import retur inbound',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function receiptsImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new InboundReceiptsImport();
        DB::beginTransaction();
        try {
            Excel::import($import, $request->file('file'));
            $groups = $import->groups ?? [];
            if (empty($groups)) {
                throw ValidationException::withMessages([
                    'file' => 'Tidak ada data valid untuk diimport',
                ]);
            }

            $createdTx = 0;
            $createdItems = 0;
            foreach ($groups as $group) {
                $transactedAt = now();
                if (!empty($group['transacted_at'])) {
                    try {
                        $transactedAt = Carbon::parse($group['transacted_at']);
                    } catch (\Throwable $e) {
                        throw ValidationException::withMessages([
                            'file' => 'Format transacted_at tidak valid: '.$group['transacted_at'],
                        ]);
                    }
                }
                $tx = InboundTransaction::create([
                    'code' => $this->generateCode('INB-RCV'),
                    'type' => 'receipt',
                    'ref_no' => $group['ref_no'] ?? null,
                    'note' => $group['note'] ?? null,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'pending',
                ]);
                $createdTx++;

                foreach ($group['items'] as $row) {
                    InboundItem::create([
                        'inbound_transaction_id' => $tx->id,
                        'item_id' => $row['item_id'],
                        'qty' => $row['qty'],
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;

                    StockService::mutate([
                        'item_id' => $row['item_id'],
                        'direction' => 'in',
                        'qty' => $row['qty'],
                        'source_type' => 'inbound',
                        'source_subtype' => 'receipt',
                        'source_id' => $tx->id,
                        'source_code' => $tx->code,
                        'note' => $row['note'] ?? null,
                        'occurred_at' => $transactedAt,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import penerimaan barang berhasil',
                'transactions' => $createdTx,
                'items' => $createdItems,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import penerimaan barang',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function index(string $type, string $pageTitle, string $routeBase)
    {
        $items = Item::orderBy('name')->get(['id', 'sku', 'name']);
        $baseOptions = $this->typeOptions();
        $typeOptions = ['all' => 'Semua'] + $baseOptions;
        $routeMap = [
            'receipt' => [
                'store' => route('admin.inbound.receipts.store'),
                'show' => route('admin.inbound.receipts.show', ':id'),
                'update' => route('admin.inbound.receipts.update', ':id'),
                'delete' => route('admin.inbound.receipts.destroy', ':id'),
                'detail' => route('admin.inbound.receipts.detail', ':id'),
                'approve' => route('admin.inbound.receipts.approve', ':id'),
            ],
            'return' => [
                'store' => route('admin.inbound.returns.store'),
                'show' => route('admin.inbound.returns.show', ':id'),
                'update' => route('admin.inbound.returns.update', ':id'),
                'delete' => route('admin.inbound.returns.destroy', ':id'),
                'detail' => route('admin.inbound.returns.detail', ':id'),
                'approve' => route('admin.inbound.returns.approve', ':id'),
            ],
            'manual' => [
                'store' => route('admin.inbound.manuals.store'),
                'show' => route('admin.inbound.manuals.show', ':id'),
                'update' => route('admin.inbound.manuals.update', ':id'),
                'delete' => route('admin.inbound.manuals.destroy', ':id'),
                'detail' => route('admin.inbound.manuals.detail', ':id'),
                'approve' => route('admin.inbound.manuals.approve', ':id'),
            ],
        ];

        return view('admin.stock-flow.index', [
            'pageTitle' => $pageTitle,
            'dataUrl' => route("admin.inbound.{$routeBase}.data"),
            'storeUrl' => route("admin.inbound.{$routeBase}.store"),
            'showUrlTpl' => route("admin.inbound.{$routeBase}.show", ':id'),
            'updateUrlTpl' => route("admin.inbound.{$routeBase}.update", ':id'),
            'deleteUrlTpl' => route("admin.inbound.{$routeBase}.destroy", ':id'),
            'detailUrlTpl' => route("admin.inbound.{$routeBase}.detail", ':id'),
            'items' => $items,
            'typeOptions' => $typeOptions,
            'typeDefault' => $type,
            'routeMap' => $routeMap,
            'importUrl' => match ($type) {
                'receipt' => route('admin.inbound.receipts.import'),
                'return' => route('admin.inbound.returns.import'),
                'manual' => route('admin.inbound.manuals.import'),
                default => null,
            },
            'importTitle' => match ($type) {
                'receipt' => 'Import Penerimaan Barang',
                'return' => 'Import Retur Inbound',
                'manual' => 'Import Manual Inbound',
                default => null,
            },
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

        $query = InboundTransaction::query()
            ->with(['items.item', 'creator'])
            ->select([
                'inbound_transactions.id',
                'inbound_transactions.code',
                'inbound_transactions.transacted_at',
                'inbound_transactions.type',
                'inbound_transactions.ref_no',
                'inbound_transactions.note',
                'inbound_transactions.status',
                'inbound_transactions.created_by',
            ])
            ->orderBy('inbound_transactions.transacted_at', 'desc');
        if ($baseType) {
            $query->where('inbound_transactions.type', $baseType);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('inbound_transactions.code', 'like', "%{$search}%")
                    ->orWhere('inbound_transactions.ref_no', 'like', "%{$search}%")
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($query, $request);

        $recordsTotalQuery = InboundTransaction::query();
        if ($baseType) {
            $recordsTotalQuery->where('type', $baseType);
        }
        $recordsTotal = $recordsTotalQuery->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $ts = $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i') : '';
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
            $totalQty = (int) $items->sum('qty');
            return [
                'id' => $row->id,
                'code' => $row->code,
                'transacted_at' => $ts,
                'submit_by' => $row->creator?->name ?? '-',
                'item' => $itemLabel ?: '-',
                'qty' => $totalQty,
                'note' => $row->note ?? '',
                'type' => $row->type,
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

    private function show(string $type, int $id)
    {
        $tx = InboundTransaction::with('items')
            ->where('type', $type)
            ->findOrFail($id);

        return response()->json([
            'id' => $tx->id,
            'code' => $tx->code,
            'ref_no' => $tx->ref_no,
            'note' => $tx->note,
            'status' => $tx->status ?? 'pending',
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
        $tx = InboundTransaction::with(['items.item'])
            ->where('type', $type)
            ->findOrFail($id);

        $totalQty = $tx->items->sum('qty');

        return view('admin.stock-flow.detail', [
            'pageTitle' => $pageTitle,
            'transaction' => $tx,
            'totalQty' => $totalQty,
            'backUrl' => route("admin.inbound.{$routeBase}.index"),
        ]);
    }

    private function store(Request $request, string $type)
    {
        $validated = $this->validatePayload($request);

        $prefix = match ($type) {
            'receipt' => 'INB-RCV',
            'return' => 'INB-RET',
            default => 'INB-MNL',
        };

        $code = $this->generateCode($prefix);
        $transactedAt = $validated['transacted_at'] ?? now();

        DB::beginTransaction();
        try {
            $tx = InboundTransaction::create([
                'code' => $code,
                'type' => $type,
                'ref_no' => $validated['ref_no'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $row) {
                InboundItem::create([
                    'inbound_transaction_id' => $tx->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);

                StockService::mutate([
                    'item_id' => $row['item_id'],
                    'direction' => 'in',
                    'qty' => $row['qty'],
                    'source_type' => 'inbound',
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
                'message' => 'Gagal menyimpan inbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Inbound berhasil disimpan',
        ]);
    }

    private function update(Request $request, string $type, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $tx = InboundTransaction::where('type', $type)->findOrFail($id);
            if (($tx->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa diubah'], 422);
            }

            StockService::rollbackBySource('inbound', $tx->id);
            StockMutation::where('source_type', 'inbound')->where('source_id', $tx->id)->delete();
            InboundItem::where('inbound_transaction_id', $tx->id)->delete();

            $tx->update([
                'ref_no' => $validated['ref_no'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? $tx->transacted_at,
            ]);

            foreach ($validated['items'] as $row) {
                InboundItem::create([
                    'inbound_transaction_id' => $tx->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);

                StockService::mutate([
                    'item_id' => $row['item_id'],
                    'direction' => 'in',
                    'qty' => $row['qty'],
                    'source_type' => 'inbound',
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
                'message' => 'Gagal memperbarui inbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Inbound berhasil diperbarui',
        ]);
    }

    private function destroy(string $type, int $id)
    {
        DB::beginTransaction();
        try {
            $tx = InboundTransaction::where('type', $type)->findOrFail($id);
            if (($tx->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa dihapus'], 422);
            }

            StockService::rollbackBySource('inbound', $tx->id);
            StockMutation::where('source_type', 'inbound')->where('source_id', $tx->id)->delete();
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
                'message' => 'Gagal menghapus inbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Inbound berhasil dihapus',
        ]);
    }

    private function approve(string $type, int $id)
    {
        $tx = InboundTransaction::where('type', $type)->findOrFail($id);
        if (($tx->status ?? 'pending') === 'approved') {
            return response()->json(['message' => 'Data sudah disetujui']);
        }
        $tx->status = 'approved';
        $tx->approved_at = now();
        $tx->approved_by = auth()->id();
        $tx->save();

        return response()->json(['message' => 'Inbound berhasil disetujui']);
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
                'items' => 'Item tidak boleh duplikat pada inbound',
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
            'receipt' => 'Penerimaan Barang',
            'return' => 'Retur',
            'manual' => 'Manual',
            'opening' => 'Saldo Awal',
        ];
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('inbound_transactions.transacted_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('inbound_transactions.transacted_at', '<=', $to);
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
