<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\Resi;
use App\Models\ReturnReason;
use App\Models\StockMutation;
use App\Exports\InboundReturnsExport;
use App\Exports\InboundReturnsTemplateExport;
use App\Imports\InboundReceiptsImport;
use App\Imports\InboundReturnsImport;
use App\Support\DamagedStockService;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function returnsCreate()
    {
        return $this->returnForm('create');
    }

    public function returnsEdit(int $id)
    {
        $tx = InboundTransaction::with(['items.item', 'items.returnReason', 'resi.kurir'])
            ->where('type', 'return')
            ->findOrFail($id);

        if (($tx->status ?? 'pending') === 'finalized') {
            return redirect()
                ->route('admin.inbound.returns.detail', $tx->id)
                ->withErrors(['return' => 'Retur yang sudah finalisasi tidak bisa diubah.']);
        }

        return $this->returnForm('edit', $tx);
    }

    public function returnsLookupResi(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
        ]);

        $code = trim($validated['code']);
        $resi = Resi::query()
            ->with(['details', 'kurir'])
            ->where('no_resi', $code)
            ->orWhere('id_pesanan', $code)
            ->first();

        if (!$resi) {
            return response()->json([
                'found' => false,
                'message' => 'Resi tidak ditemukan. Silakan input item retur manual.',
                'scanned_code' => $code,
            ]);
        }

        $skus = $resi->details->pluck('sku')->filter()->unique()->values();
        $itemMap = Item::active()
            ->whereIn('sku', $skus)
            ->get(['id', 'sku', 'name'])
            ->keyBy('sku');

        return response()->json([
            'found' => true,
            'resi' => [
                'id' => $resi->id,
                'id_pesanan' => $resi->id_pesanan,
                'no_resi' => $resi->no_resi,
                'kurir' => $resi->kurir?->name,
                'tanggal_pesanan' => $resi->tanggal_pesanan?->format('Y-m-d'),
                'tanggal_upload' => $resi->tanggal_upload?->format('Y-m-d'),
            ],
            'items' => $resi->details->map(function ($detail) use ($itemMap) {
                $item = $itemMap->get($detail->sku);
                return [
                    'item_id' => $item?->id,
                    'sku' => $detail->sku,
                    'name' => $item?->name,
                    'qty_resi' => (int) $detail->qty,
                    'item_found' => (bool) $item,
                ];
            })->values(),
        ]);
    }

    public function receiptsData(Request $request)
    {
        return $this->data($request, 'receipt');
    }

    public function returnsData(Request $request)
    {
        return $this->data($request, 'return');
    }

    public function receiptsStore(Request $request)
    {
        return $this->store($request, 'receipt');
    }

    public function returnsStore(Request $request)
    {
        return $this->store($request, 'return');
    }

    public function receiptsShow(int $id)
    {
        return $this->show('receipt', $id);
    }

    public function returnsShow(int $id)
    {
        return $this->show('return', $id);
    }

    public function receiptsDetail(int $id)
    {
        return $this->detail('receipt', 'Inbound - Penerimaan Barang', 'receipts', $id);
    }

    public function returnsDetail(int $id)
    {
        return $this->detail('return', 'Inbound - Retur', 'returns', $id);
    }

    public function receiptsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'receipt', $id);
    }

    public function returnsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'return', $id);
    }

    public function receiptsDestroy(int $id)
    {
        return $this->destroy('receipt', $id);
    }

    public function returnsDestroy(int $id)
    {
        return $this->destroy('return', $id);
    }

    public function receiptsApprove(int $id)
    {
        return $this->approve('receipt', $id);
    }

    public function returnsApprove(int $id)
    {
        return $this->approve('return', $id);
    }

    public function returnsFinalize(int $id)
    {
        return $this->finalizeReturn($id);
    }

    public function returnsTemplate()
    {
        return Excel::download(
            new InboundReturnsTemplateExport(),
            'template-import-retur-inbound.xlsx'
        );
    }

    public function returnsExport(Request $request)
    {
        $dateTo = $request->input('date_to') ?: now()->toDateString();
        $dateFrom = $request->input('date_from') ?: now()->subDays(6)->toDateString();
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', '')),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        $filename = sprintf(
            'retur-inbound-%s-sd-%s.xlsx',
            Carbon::parse($dateFrom)->format('Ymd'),
            Carbon::parse($dateTo)->format('Ymd')
        );

        return Excel::download(new InboundReturnsExport($filters), $filename);
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
                    'resi_id' => $group['resi_id'] ?? null,
                    'return_resi_no' => $group['return_resi_no'] ?? null,
                    'note' => $group['note'] ?? null,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                ]);
                $createdTx++;

                foreach ($group['items'] as $row) {
                    InboundItem::create([
                        'inbound_transaction_id' => $tx->id,
                        'item_id' => $row['item_id'],
                        'qty' => $row['qty_received'] ?? $row['qty'],
                        'qty_resi' => $row['qty_resi'] ?? ($row['qty_received'] ?? $row['qty']),
                        'qty_received' => $row['qty_received'] ?? $row['qty'],
                        'qty_difference' => $row['qty_difference'] ?? max(($row['qty_resi'] ?? ($row['qty_received'] ?? $row['qty'])) - ($row['qty_received'] ?? $row['qty']), 0),
                        'qty_good' => $row['qty_good'] ?? 0,
                        'qty_damaged' => $row['qty_damaged'] ?? ($row['qty_received'] ?? $row['qty']),
                        'return_reason_id' => $row['return_reason_id'] ?? null,
                        'return_reason_note' => $row['return_reason_note'] ?? null,
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;

                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import retur inbound berhasil masuk Gudang Retur',
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
                        'qty_received' => $row['qty'],
                        'qty_good' => $row['qty'],
                        'qty_damaged' => 0,
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;

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
        $items = Item::active()->orderBy('name')->get(['id', 'sku', 'name']);
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
                'create' => route('admin.inbound.returns.create'),
                'show' => route('admin.inbound.returns.show', ':id'),
                'edit' => route('admin.inbound.returns.edit', ':id'),
                'update' => route('admin.inbound.returns.update', ':id'),
                'delete' => route('admin.inbound.returns.destroy', ':id'),
                'detail' => route('admin.inbound.returns.detail', ':id'),
                'approve' => route('admin.inbound.returns.approve', ':id'),
                'finalize' => route('admin.inbound.returns.finalize', ':id'),
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
                default => null,
            },
            'importTitle' => match ($type) {
                'receipt' => 'Import Penerimaan Barang',
                'return' => 'Import Retur Inbound',
                default => null,
            },
            'templateUrl' => match ($type) {
                'return' => route('admin.inbound.returns.template'),
                default => null,
            },
            'exportUrl' => match ($type) {
                'return' => route('admin.inbound.returns.export'),
                default => null,
            },
            'defaultDateFrom' => $type === 'return' ? now()->subDays(6)->toDateString() : null,
            'defaultDateTo' => $type === 'return' ? now()->toDateString() : null,
        ]);
    }

    private function returnForm(string $mode, ?InboundTransaction $transaction = null)
    {
        $items = Item::active()->orderBy('name')->get(['id', 'sku', 'name']);
        $returnReasons = ReturnReason::active()->orderBy('name')->get(['id', 'name']);

        return view('admin.inbound.returns.form', [
            'mode' => $mode,
            'pageTitle' => $mode === 'edit' ? 'Edit Retur Inbound' : 'Tambah Retur Inbound',
            'transaction' => $transaction,
            'items' => $items,
            'returnReasons' => $returnReasons,
            'lookupUrl' => route('admin.inbound.returns.lookup-resi'),
            'storeUrl' => route('admin.inbound.returns.store'),
            'updateUrl' => $transaction ? route('admin.inbound.returns.update', $transaction->id) : null,
            'backUrl' => route('admin.inbound.returns.index'),
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
            ->with(['items.item', 'items.returnReason', 'creator', 'resi'])
            ->select([
                'inbound_transactions.id',
                'inbound_transactions.code',
                'inbound_transactions.transacted_at',
                'inbound_transactions.type',
                'inbound_transactions.ref_no',
                'inbound_transactions.resi_id',
                'inbound_transactions.return_resi_no',
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
                    ->orWhere('inbound_transactions.return_resi_no', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('no_resi', 'like', "%{$search}%")
                            ->orWhere('id_pesanan', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $status = trim((string) $request->input('status', ''));
        if (in_array($status, ['pending', 'approved', 'finalized'], true)) {
            $query->where('inbound_transactions.status', $status);
        }

        $this->applyDateFilter($query, $request, $type === 'return');

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
            $labels = $items->map(function ($it) use ($row) {
                $sku = trim($it->item?->sku ?? '');
                if ($sku === '') {
                    return '';
                }
                if (($row->type ?? '') === 'return') {
                    return sprintf(
                        '%s (resi %d, terima %d, selisih %d, bagus %d, rusak %d)',
                        $sku,
                        (int) ($it->qty_resi ?? $it->qty_received ?? $it->qty ?? 0),
                        (int) ($it->qty_received ?? $it->qty ?? 0),
                        (int) ($it->qty_difference ?? 0),
                        (int) ($it->qty_good ?? 0),
                        (int) ($it->qty_damaged ?? 0)
                    );
                }

                return sprintf('%s (%d)', $sku, (int) ($it->qty ?? 0));
            })->filter()->values();
            $itemLabel = $labels->implode(', ');
            $totalQty = (int) $items->sum(fn ($it) => (int) ($it->qty_received ?? $it->qty ?? 0));
            $returnItems = $items->map(function ($it) {
                return [
                    'sku' => $it->item?->sku ?? '-',
                    'name' => $it->item?->name ?? '-',
                    'qty_resi' => (int) ($it->qty_resi ?? $it->qty_received ?? $it->qty ?? 0),
                    'qty_received' => (int) ($it->qty_received ?? $it->qty ?? 0),
                    'qty_difference' => (int) ($it->qty_difference ?? 0),
                    'qty_good' => (int) ($it->qty_good ?? 0),
                    'qty_damaged' => (int) ($it->qty_damaged ?? 0),
                    'return_reason' => $it->returnReason?->name ?? null,
                    'note' => $it->note ?? null,
                ];
            })->values();
            return [
                'id' => $row->id,
                'code' => $row->code,
                'transacted_at' => $ts,
                'submit_by' => $row->creator?->name ?? '-',
                'return_resi' => $row->return_resi_no ?: ($row->resi?->no_resi ?: $row->resi?->id_pesanan),
                'item' => $itemLabel ?: '-',
                'return_items' => $returnItems,
                'qty' => $totalQty,
                'qty_resi' => (int) $items->sum(fn ($it) => (int) ($it->qty_resi ?? $it->qty_received ?? $it->qty ?? 0)),
                'qty_difference' => (int) $items->sum(fn ($it) => (int) ($it->qty_difference ?? 0)),
                'note' => $row->note ?? '',
                'type' => $row->type,
                'status' => $row->status ?? 'pending',
                'return_warehouse_qty' => ($row->type ?? '') === 'return' && ($row->status ?? 'pending') === 'approved'
                    ? (int) $items->sum(fn ($it) => (int) ($it->qty_received ?? $it->qty ?? 0))
                    : 0,
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
        $tx = InboundTransaction::with(['items.returnReason', 'resi'])
            ->where('type', $type)
            ->findOrFail($id);

        return response()->json([
            'id' => $tx->id,
            'code' => $tx->code,
            'ref_no' => $tx->ref_no,
            'resi_id' => $tx->resi_id,
            'return_resi_no' => $tx->return_resi_no,
            'note' => $tx->note,
            'status' => $tx->status ?? 'pending',
            'finalized_at' => $tx->finalized_at?->format('Y-m-d H:i'),
            'transacted_at' => $tx->transacted_at?->format('Y-m-d\TH:i'),
            'items' => $tx->items->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'qty' => $item->qty,
                    'qty_resi' => $item->qty_resi ?? $item->qty_received ?? $item->qty,
                    'qty_received' => $item->qty_received ?: $item->qty,
                    'qty_difference' => $item->qty_difference ?? 0,
                    'qty_good' => $item->qty_good ?? 0,
                    'qty_damaged' => $item->qty_damaged ?? 0,
                    'return_reason_id' => $item->return_reason_id,
                    'return_reason_note' => $item->return_reason_note ?? '',
                    'note' => $item->note ?? '',
                ];
            })->values(),
        ]);
    }

    private function detail(string $type, string $pageTitle, string $routeBase, int $id)
    {
        $tx = InboundTransaction::with(['items.item', 'items.returnReason', 'creator', 'approver', 'finalizer', 'resi.kurir'])
            ->where('type', $type)
            ->findOrFail($id);

        $totalQty = $tx->items->sum(fn ($item) => (int) ($item->qty_received ?? $item->qty ?? 0));

        return view('admin.stock-flow.detail', [
            'pageTitle' => $pageTitle,
            'transaction' => $tx,
            'totalQty' => $totalQty,
            'backUrl' => route("admin.inbound.{$routeBase}.index"),
        ]);
    }

    private function store(Request $request, string $type)
    {
        $validated = $this->validatePayload($request, $type);

        $prefix = match ($type) {
            'receipt' => 'INB-RCV',
            'return' => 'INB-RET',
            default => 'INB-RCV',
        };

        $code = $this->generateCode($prefix);
        $transactedAt = $validated['transacted_at'] ?? now();
        $status = $type === 'return' ? 'approved' : 'pending';
        $approvedAt = $type === 'return' ? now() : null;
        $approvedBy = $type === 'return' ? auth()->id() : null;

        DB::beginTransaction();
        try {
            $tx = InboundTransaction::create([
                'code' => $code,
                'type' => $type,
                'ref_no' => $validated['ref_no'] ?? null,
                'resi_id' => $validated['resi_id'] ?? null,
                'return_resi_no' => $validated['return_resi_no'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
                'status' => $status,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
            ]);

            foreach ($validated['items'] as $row) {
                InboundItem::create([
                    'inbound_transaction_id' => $tx->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'qty_resi' => $row['qty_resi'] ?? $row['qty'],
                    'qty_received' => $row['qty_received'] ?? $row['qty'],
                    'qty_difference' => $row['qty_difference'] ?? 0,
                    'qty_good' => $row['qty_good'] ?? $row['qty'],
                    'qty_damaged' => $row['qty_damaged'] ?? 0,
                    'return_reason_id' => $row['return_reason_id'] ?? null,
                    'return_reason_note' => $row['return_reason_note'] ?? null,
                    'note' => $row['note'] ?? null,
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
            'message' => $type === 'return'
                ? 'Retur berhasil masuk Gudang Retur. Lakukan finalisasi untuk distribusi stok.'
                : 'Inbound berhasil disimpan',
        ]);
    }

    private function update(Request $request, string $type, int $id)
    {
        $validated = $this->validatePayload($request, $type);

        DB::beginTransaction();
        try {
            $tx = InboundTransaction::where('type', $type)->findOrFail($id);
            $status = $tx->status ?? 'pending';
            $isEditableApprovedReturn = $type === 'return' && $status === 'approved';
            if ($status === 'finalized' || ($status === 'approved' && !$isEditableApprovedReturn)) {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah diproses dan tidak bisa diubah'], 422);
            }

            StockService::rollbackBySource('inbound', $tx->id);
            StockMutation::where('source_type', 'inbound')->where('source_id', $tx->id)->delete();
            InboundItem::where('inbound_transaction_id', $tx->id)->delete();

            $tx->update([
                'ref_no' => $validated['ref_no'] ?? null,
                'resi_id' => $validated['resi_id'] ?? null,
                'return_resi_no' => $validated['return_resi_no'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? $tx->transacted_at,
            ]);

            foreach ($validated['items'] as $row) {
                InboundItem::create([
                    'inbound_transaction_id' => $tx->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'qty_resi' => $row['qty_resi'] ?? $row['qty'],
                    'qty_received' => $row['qty_received'] ?? $row['qty'],
                    'qty_difference' => $row['qty_difference'] ?? 0,
                    'qty_good' => $row['qty_good'] ?? $row['qty'],
                    'qty_damaged' => $row['qty_damaged'] ?? 0,
                    'return_reason_id' => $row['return_reason_id'] ?? null,
                    'return_reason_note' => $row['return_reason_note'] ?? null,
                    'note' => $row['note'] ?? null,
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
            $status = $tx->status ?? 'pending';
            $canDeleteApprovedReturn = $type === 'return' && $status === 'approved';
            if ($status === 'finalized' || ($status === 'approved' && !$canDeleteApprovedReturn)) {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah diproses dan tidak bisa dihapus'], 422);
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
        DB::beginTransaction();
        try {
            $tx = InboundTransaction::where('type', $type)
                ->lockForUpdate()
                ->findOrFail($id);

            if (($tx->status ?? 'pending') === 'approved') {
                DB::commit();
                return response()->json(['message' => 'Data sudah disetujui']);
            }
            if (($tx->status ?? 'pending') === 'finalized') {
                DB::commit();
                return response()->json(['message' => 'Data sudah finalisasi']);
            }

            if ($type !== 'return') {
                $this->postStockMovements($tx, $type);
            } else {
                $this->assertInboundReturnBalanced($tx);
            }

            $tx->status = 'approved';
            $tx->approved_at = now();
            $tx->approved_by = auth()->id();
            $tx->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyetujui inbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => $type === 'return'
                ? 'Retur berhasil masuk Gudang Retur. Lakukan finalisasi untuk distribusi stok.'
                : 'Inbound berhasil disetujui',
        ]);
    }

    private function finalizeReturn(int $id)
    {
        DB::beginTransaction();
        try {
            $tx = InboundTransaction::where('type', 'return')
                ->lockForUpdate()
                ->findOrFail($id);

            if (($tx->status ?? 'pending') === 'finalized') {
                DB::commit();
                return response()->json(['message' => 'Retur sudah finalisasi']);
            }

            if (($tx->status ?? 'pending') !== 'approved') {
                DB::rollBack();
                return response()->json([
                    'message' => 'Retur harus disetujui dan masuk Gudang Retur sebelum finalisasi.',
                ], 422);
            }

            $this->assertInboundReturnBalanced($tx);
            $this->postInboundReturnToDamagedStock($tx);

            $tx->status = 'finalized';
            $tx->finalized_at = now();
            $tx->finalized_by = auth()->id();
            $tx->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal finalisasi retur',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Retur berhasil difinalisasi']);
    }

    private function postStockMovements(InboundTransaction $tx, string $type): void
    {
        if ($type === 'return') {
            $this->postInboundReturnToDamagedStock($tx);
            return;
        }

        $tx->loadMissing('items');
        foreach ($tx->items as $row) {
            StockService::mutate([
                'item_id' => $row->item_id,
                'direction' => 'in',
                'qty' => (int) ($row->qty_good ?: $row->qty),
                'source_type' => 'inbound',
                'source_subtype' => $type,
                'source_id' => $tx->id,
                'source_code' => $tx->code,
                'note' => $row->note ?? null,
                'occurred_at' => $tx->transacted_at ?? now(),
                'created_by' => auth()->id(),
                'idempotency_key' => StockService::idempotencyKey(['stock', 'inbound', $type, $tx->id, $row->item_id]),
            ]);
        }
    }

    private function assertInboundReturnBalanced(InboundTransaction $tx): void
    {
        $tx->loadMissing('items.item');
        foreach ($tx->items as $row) {
            $qtyResi = (int) ($row->qty_resi ?? $row->qty_received ?? $row->qty ?? 0);
            $received = (int) ($row->qty_received ?? $row->qty ?? 0);
            $good = (int) ($row->qty_good ?? 0);
            $damaged = (int) ($row->qty_damaged ?? 0);
            if ($qtyResi > 0 && $received > $qtyResi) {
                $sku = $row->item?->sku ?? 'item '.$row->item_id;
                throw ValidationException::withMessages([
                    'items' => "Qty diterima tidak boleh lebih besar dari qty resi untuk {$sku}.",
                ]);
            }
            if ($good + $damaged !== $received) {
                $sku = $row->item?->sku ?? 'item '.$row->item_id;
                throw ValidationException::withMessages([
                    'items' => "Qty OK + qty reject harus sama dengan qty diterima untuk {$sku}.",
                ]);
            }
        }
    }

    private function postInboundReturnToDamagedStock(InboundTransaction $tx): void
    {
        $tx->loadMissing('items');

        $hasDamagedItems = $tx->items->contains(fn ($row) => (int) ($row->qty_damaged ?? 0) > 0);
        $damage = null;

        if ($hasDamagedItems) {
            $damage = DamagedGood::where('source_type', 'inbound_return')
                ->where('source_ref', $tx->code)
                ->lockForUpdate()
                ->first();

            if (!$damage) {
                $damage = DamagedGood::create([
                    'code' => $this->generateCode('DMG-RET'),
                    'source_type' => 'inbound_return',
                    'source_ref' => $tx->code,
                    'inbound_transaction_id' => $tx->id,
                    'transacted_at' => $tx->transacted_at ?? now(),
                    'note' => 'Otomatis dari inbound retur '.$tx->code,
                    'created_by' => auth()->id(),
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                ]);

                foreach ($tx->items as $row) {
                    $damagedQty = (int) ($row->qty_damaged ?? 0);
                    if ($damagedQty <= 0) {
                        continue;
                    }

                    DamagedGoodItem::create([
                        'damaged_good_id' => $damage->id,
                        'item_id' => $row->item_id,
                        'qty' => $damagedQty,
                        'note' => $row->note ?? null,
                    ]);
                }
            } elseif (($damage->status ?? 'pending') !== 'approved') {
                $damage->status = 'approved';
                $damage->approved_at = now();
                $damage->approved_by = auth()->id();
                $damage->save();
            }
        }

        foreach ($tx->items as $row) {
            $goodQty = (int) ($row->qty_good ?? 0);
            if ($goodQty > 0) {
                StockService::mutate([
                    'item_id' => $row->item_id,
                    'direction' => 'in',
                    'qty' => $goodQty,
                    'source_type' => 'inbound',
                    'source_subtype' => 'return_good',
                    'source_id' => $tx->id,
                    'source_code' => $tx->code,
                    'note' => $row->note ?? 'Inbound retur barang bagus',
                    'occurred_at' => $tx->transacted_at ?? now(),
                    'created_by' => auth()->id(),
                    'idempotency_key' => StockService::idempotencyKey(['stock', 'inbound-return-good', $tx->id, $row->item_id]),
                ]);
            }

            $damagedQty = (int) ($row->qty_damaged ?? 0);
            if ($damagedQty > 0) {
                DamagedStockService::mutate([
                    'item_id' => $row->item_id,
                    'direction' => 'in',
                    'qty' => $damagedQty,
                    'source_type' => 'inbound_return',
                    'source_subtype' => 'approval',
                    'source_id' => $tx->id,
                    'source_code' => $tx->code,
                    'note' => $row->note ?? 'Inbound retur masuk stok barang rusak',
                    'occurred_at' => $tx->transacted_at ?? now(),
                    'created_by' => auth()->id(),
                    'idempotency_key' => DamagedStockService::idempotencyKey(['damaged-stock', 'inbound-return', $tx->id, $row->item_id]),
                ]);
            }
        }
    }

    private function validatePayload(Request $request, ?string $type = null): array
    {
        $isReturn = $type === 'return';
        $rules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('is_active', true)],
            'items.*.note' => ['nullable', 'string'],
            'ref_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
        ];
        if ($isReturn) {
            $rules['resi_id'] = ['nullable', 'integer', 'exists:resis,id'];
            $rules['return_resi_no'] = ['nullable', 'string', 'max:100'];
            $rules['items.*.qty_resi'] = ['nullable', 'integer', 'min:1'];
            $rules['items.*.qty_received'] = ['required', 'integer', 'min:0'];
            $rules['items.*.qty_good'] = ['required', 'integer', 'min:0'];
            $rules['items.*.qty_damaged'] = ['required', 'integer', 'min:0'];
            $rules['items.*.return_reason_id'] = ['nullable', 'integer', 'exists:return_reasons,id'];
            $rules['items.*.return_reason_note'] = ['nullable', 'string'];
        } else {
            $rules['items.*.qty'] = ['required', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules);

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($row) => (int) ($row['item_id'] ?? 0) > 0)
            ->map(function ($row) use ($isReturn) {
                if ($isReturn) {
                    $received = (int) ($row['qty_received'] ?? 0);
                    $qtyResi = (int) ($row['qty_resi'] ?? $received);
                    $good = (int) ($row['qty_good'] ?? 0);
                    $damaged = (int) ($row['qty_damaged'] ?? 0);
                    return [
                        'item_id' => (int) $row['item_id'],
                        'qty' => $received,
                        'qty_resi' => $qtyResi,
                        'qty_received' => $received,
                        'qty_difference' => max($qtyResi - $received, 0),
                        'qty_good' => $good,
                        'qty_damaged' => $damaged,
                        'return_reason_id' => !empty($row['return_reason_id']) ? (int) $row['return_reason_id'] : null,
                        'return_reason_note' => $row['return_reason_note'] ?? null,
                        'note' => $row['note'] ?? null,
                    ];
                }

                $qty = (int) ($row['qty'] ?? 0);
                return [
                    'item_id' => (int) $row['item_id'],
                    'qty' => $qty,
                    'qty_resi' => $qty,
                    'qty_received' => $qty,
                    'qty_difference' => 0,
                    'qty_good' => $qty,
                    'qty_damaged' => 0,
                    'note' => $row['note'] ?? null,
                ];
            })->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Minimal 1 item diperlukan',
            ]);
        }

        if ($isReturn) {
            foreach ($items as $idx => $row) {
                if ((int) $row['qty_resi'] <= 0) {
                    throw ValidationException::withMessages(["items.{$idx}.qty_resi" => 'Qty resi wajib lebih dari 0']);
                }
                if ((int) $row['qty_received'] > (int) $row['qty_resi']) {
                    throw ValidationException::withMessages(["items.{$idx}.qty_received" => 'Qty diterima tidak boleh lebih besar dari qty resi']);
                }
                if ((int) $row['qty_good'] + (int) $row['qty_damaged'] !== (int) $row['qty_received']) {
                    throw ValidationException::withMessages(["items.{$idx}.qty_received" => 'Qty bagus + qty rusak harus sama dengan qty diterima']);
                }
            }
        }

        $duplicates = $items->groupBy('item_id')->filter(fn ($rows) => $rows->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item tidak boleh duplikat pada inbound',
            ]);
        }

        $normalized = $items->groupBy('item_id')->map(function ($rows, $itemId) {
            $qty = $rows->sum('qty');
            $qtyResi = $rows->sum('qty_resi');
            $qtyReceived = $rows->sum('qty_received');
            $qtyGood = $rows->sum('qty_good');
            $qtyDamaged = $rows->sum('qty_damaged');
            $note = $rows->pluck('note')->first(fn ($n) => $n !== null && $n !== '') ?? null;
            $reasonId = $rows->pluck('return_reason_id')->first(fn ($n) => $n !== null && $n !== '') ?? null;
            $reasonNote = $rows->pluck('return_reason_note')->first(fn ($n) => $n !== null && $n !== '') ?? null;
            return [
                'item_id' => (int) $itemId,
                'qty' => $qty,
                'qty_resi' => $qtyResi,
                'qty_received' => $qtyReceived,
                'qty_difference' => max($qtyResi - $qtyReceived, 0),
                'qty_good' => $qtyGood,
                'qty_damaged' => $qtyDamaged,
                'return_reason_id' => $reasonId,
                'return_reason_note' => $reasonNote,
                'note' => $note,
            ];
        })->values()->all();

        $validated['items'] = $normalized;
        if (!empty($validated['transacted_at'])) {
            $validated['transacted_at'] = Carbon::parse($validated['transacted_at']);
        } else {
            $validated['transacted_at'] = null;
        }

        if ($isReturn && empty($validated['return_resi_no']) && !empty($validated['resi_id'])) {
            $resi = Resi::find($validated['resi_id']);
            $validated['return_resi_no'] = $resi?->no_resi ?: $resi?->id_pesanan;
        }

        return $validated;
    }

    private function typeOptions(): array
    {
        return [
            'receipt' => 'Penerimaan Barang',
            'return' => 'Retur',
            'opening' => 'Saldo Awal',
        ];
    }

    private function applyDateFilter($query, Request $request, bool $defaultLastSevenDays = false): void
    {
        $dateFrom = $request->input('date_from') ?: ($defaultLastSevenDays ? now()->subDays(6)->toDateString() : null);
        $dateTo = $request->input('date_to') ?: ($defaultLastSevenDays ? now()->toDateString() : null);

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
