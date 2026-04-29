<?php

namespace App\Http\Controllers\Qc;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\QcScanResi;
use App\Models\QcScanResiItem;
use App\Models\QcScanSession;
use App\Models\QcTransitItem;
use App\Models\Resi;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QcScanController extends Controller
{
    public function current()
    {
        $session = $this->currentSession();

        return response()->json([
            'session' => $session ? $this->serializeSession($session) : null,
        ]);
    }

    public function start()
    {
        $session = $this->ensureSession();

        return response()->json([
            'session' => $this->serializeSession($session),
        ]);
    }

    public function lookupResi(Request $request)
    {
        $type = $request->input('type', 'no_resi');
        $code = trim((string) $request->input('code', ''));

        if ($code === '') {
            return response()->json(['message' => 'Kode tidak boleh kosong.'], 422);
        }

        if (!in_array($type, ['no_resi', 'id_pesanan'], true)) {
            return response()->json(['message' => 'Tipe tidak valid.'], 422);
        }

        $resi = Resi::query()
            ->with(['details', 'kurir'])
            ->when($type === 'no_resi', fn ($q) => $q->where('no_resi', $code))
            ->when($type === 'id_pesanan', fn ($q) => $q->where('id_pesanan', $code))
            ->first();

        if (!$resi) {
            return response()->json(['message' => 'Resi tidak ditemukan.'], 422);
        }

        if (($resi->status ?? 'active') === 'canceled') {
            return response()->json(['message' => 'Resi sudah dibatalkan, tidak bisa di-QC.'], 422);
        }

        $skuTotals = $this->buildResiSkuTotals($resi);
        if (empty($skuTotals)) {
            return response()->json(['message' => 'Resi tidak memiliki detail SKU valid.'], 422);
        }

        $ledgerQty = [];
        $alreadyScanned = false;
        $isComplete = false;
        $session = $this->currentSession();
        if ($session) {
            $qcResi = QcScanResi::where('qc_scan_session_id', $session->id)
                ->where('resi_id', $resi->id)
                ->with('items')
                ->first();
            if ($qcResi) {
                $alreadyScanned = true;
                $isComplete = ($qcResi->status === 'completed');
                foreach ($qcResi->items as $item) {
                    $ledgerQty[$item->sku] = (int) $item->scanned_qty;
                }
            }
        }

        $items = collect($skuTotals)->map(fn ($qty, $sku) => [
            'sku' => $sku,
            'qty' => $qty,
            'scanned_qty' => min((int) ($ledgerQty[$sku] ?? 0), (int) $qty),
        ])->values();

        return response()->json([
            'resi' => [
                'id' => $resi->id,
                'id_pesanan' => $resi->id_pesanan,
                'no_resi' => $resi->no_resi,
                'tanggal_pesanan' => $resi->tanggal_pesanan?->format('Y-m-d'),
                'kurir_name' => $resi->kurir?->name ?? 'Tidak diketahui',
            ],
            'items' => $items,
            'already_scanned' => $alreadyScanned,
            'is_complete' => $isComplete,
        ]);
    }

    public function recordResi(Request $request)
    {
        $validated = $request->validate([
            'resi_id' => ['required', 'integer', 'exists:resis,id'],
        ]);

        $session = $this->ensureSession();
        $qcResi = DB::transaction(function () use ($session, $validated) {
            $resi = Resi::with('details')->lockForUpdate()->findOrFail((int) $validated['resi_id']);
            if (($resi->status ?? 'active') === 'canceled') {
                throw ValidationException::withMessages(['resi' => 'Resi sudah dibatalkan, tidak bisa di-QC.']);
            }

            return $this->ensureQcResi($session, $resi);
        });

        return response()->json([
            'message' => 'Resi tercatat dalam sesi QC.',
            'status' => $qcResi->status,
            'session' => $this->serializeSession($session->fresh(['resis.resi.kurir', 'resis.items.item'])),
        ]);
    }

    public function scanItem(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'qty' => ['nullable', 'integer', 'min:1'],
            'resi_id' => ['required', 'integer', 'exists:resis,id'],
        ]);

        $code = trim((string) $validated['code']);
        $qty = (int) ($validated['qty'] ?? 1);

        try {
            $session = DB::transaction(function () use ($validated, $code, $qty) {
                $item = Item::where('sku', $code)->lockForUpdate()->first();
                if (!$item) {
                    throw ValidationException::withMessages(['code' => 'SKU tidak ditemukan pada master item.']);
                }

                $resi = Resi::with('details')->lockForUpdate()->findOrFail((int) $validated['resi_id']);
                if (($resi->status ?? 'active') === 'canceled') {
                    throw ValidationException::withMessages(['resi' => 'Resi sudah dibatalkan, tidak bisa di-QC.']);
                }

                $session = $this->ensureSession();
                $qcResi = $this->ensureQcResi($session, $resi);
                if ($qcResi->status === 'completed') {
                    throw ValidationException::withMessages(['resi' => 'Resi sudah selesai di-QC.']);
                }

                $ledger = QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)
                    ->where('sku', $item->sku)
                    ->lockForUpdate()
                    ->first();

                if (!$ledger) {
                    throw ValidationException::withMessages([
                        'code' => "SKU {$item->sku} tidak ditemukan dalam resi tersebut.",
                    ]);
                }

                $remaining = max(0, (int) $ledger->required_qty - (int) $ledger->scanned_qty);
                if ($qty > $remaining) {
                    throw ValidationException::withMessages([
                        'qty' => "Qty scan ({$qty}) melebihi sisa resi untuk SKU {$item->sku} ({$remaining}).",
                    ]);
                }

                $scanAt = now();
                $date = $session->started_at?->toDateString() ?? $scanAt->toDateString();

                $this->ensurePickingListCapacity($date, $item->sku, $qty);

                $ledger->scanned_qty = (int) $ledger->scanned_qty + $qty;
                $ledger->item_id = $item->id;
                $ledger->save();

                $transit = QcTransitItem::where('item_id', $item->id)
                    ->where('transit_date', $date)
                    ->lockForUpdate()
                    ->first();

                if ($transit) {
                    $transit->qty += $qty;
                    $transit->remaining_qty += $qty;
                    $transit->last_qc_at = $scanAt;
                    $transit->save();
                } else {
                    QcTransitItem::create([
                        'item_id' => $item->id,
                        'transit_date' => $date,
                        'qty' => $qty,
                        'remaining_qty' => $qty,
                        'last_qc_at' => $scanAt,
                    ]);
                }

                StockService::mutate([
                    'item_id' => $item->id,
                    'direction' => 'out',
                    'qty' => $qty,
                    'source_type' => 'qc_resi',
                    'source_subtype' => 'scan',
                    'source_id' => $qcResi->id,
                    'source_code' => $resi->no_resi ?: $resi->id_pesanan,
                    'note' => 'QC scan resi',
                    'occurred_at' => $scanAt,
                    'created_by' => auth()->id(),
                ]);

                $this->adjustPickingRemaining($date, $item->sku, $qty);
                $this->markCompletedIfReady($qcResi);

                $session->last_scan_at = $scanAt;
                $session->save();

                return $session->fresh(['resis.resi.kurir', 'resis.items.item']);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal memproses scan QC.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Item berhasil discan.',
            'session' => $this->serializeSession($session),
        ]);
    }

    public function searchItems(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $query = Item::query();
        if ($search !== '') {
            $query->where('sku', 'like', "%{$search}%");
        }

        return response()->json([
            'items' => $query->orderBy('sku')->get(['id', 'sku', 'name', 'address']),
        ]);
    }

    private function currentSession(): ?QcScanSession
    {
        return QcScanSession::with(['resis.resi.kurir', 'resis.items.item'])
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->whereDate('started_at', now()->toDateString())
            ->latest('id')
            ->first();
    }

    private function ensureSession(): QcScanSession
    {
        return DB::transaction(function () {
            $session = QcScanSession::where('user_id', auth()->id())
                ->where('status', 'active')
                ->whereDate('started_at', now()->toDateString())
                ->lockForUpdate()
                ->latest('id')
                ->first();

            return $session ?: QcScanSession::create([
                'code' => 'QC-'.Carbon::now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'user_id' => auth()->id(),
                'status' => 'active',
                'started_at' => now(),
            ]);
        });
    }

    private function ensureQcResi(QcScanSession $session, Resi $resi): QcScanResi
    {
        $qcResi = QcScanResi::where('qc_scan_session_id', $session->id)
            ->where('resi_id', $resi->id)
            ->lockForUpdate()
            ->first();

        if (!$qcResi) {
            $qcResi = QcScanResi::create([
                'qc_scan_session_id' => $session->id,
                'resi_id' => $resi->id,
                'status' => 'in_progress',
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
            ]);
        }

        $skuTotals = $this->buildResiSkuTotals($resi);
        if (empty($skuTotals)) {
            throw ValidationException::withMessages(['resi' => 'Resi tidak memiliki detail SKU valid.']);
        }

        $itemIdsBySku = Item::whereIn('sku', array_keys($skuTotals))->pluck('id', 'sku')->all();
        foreach ($skuTotals as $sku => $requiredQty) {
            QcScanResiItem::updateOrCreate(
                ['qc_scan_resi_id' => $qcResi->id, 'sku' => $sku],
                ['item_id' => $itemIdsBySku[$sku] ?? null, 'required_qty' => $requiredQty]
            );
        }

        return $qcResi->fresh(['resi', 'items.item']);
    }

    private function buildResiSkuTotals(Resi $resi): array
    {
        $resi->loadMissing('details');
        $totals = [];
        foreach ($resi->details as $detail) {
            $sku = trim((string) ($detail->sku ?? ''));
            $qty = (int) ($detail->qty ?? 0);
            if ($sku !== '' && $qty > 0) {
                $totals[$sku] = ($totals[$sku] ?? 0) + $qty;
            }
        }
        ksort($totals, SORT_NATURAL | SORT_FLAG_CASE);
        return $totals;
    }

    private function markCompletedIfReady(QcScanResi $qcResi): void
    {
        $items = QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)->lockForUpdate()->get();
        $complete = $items->isNotEmpty()
            && $items->every(fn ($item) => (int) $item->scanned_qty >= (int) $item->required_qty);

        if ($complete) {
            $qcResi->status = 'completed';
            $qcResi->completed_at = now();
            $qcResi->completed_by = auth()->id();
            $qcResi->save();
            return;
        }

        if ($qcResi->status !== 'in_progress') {
            $qcResi->status = 'in_progress';
            $qcResi->completed_at = null;
            $qcResi->completed_by = null;
            $qcResi->save();
        }
    }

    private function serializeSession(QcScanSession $session): array
    {
        $session->loadMissing(['resis.resi.kurir', 'resis.items.item']);
        $resis = $session->resis;
        $items = $resis->flatMap(fn ($resi) => $resi->items)->groupBy('sku')->map(function ($rows, $sku) {
            $first = $rows->first();
            return [
                'sku' => $sku,
                'name' => $first?->item?->name ?? '-',
                'qty' => (int) $rows->sum('scanned_qty'),
                'required_qty' => (int) $rows->sum('required_qty'),
            ];
        })->values();

        return [
            'id' => $session->id,
            'code' => $session->code,
            'status' => $session->status,
            'started_at' => $session->started_at?->format('Y-m-d H:i'),
            'last_scan_at' => $session->last_scan_at?->format('Y-m-d H:i'),
            'items' => $items,
            'resis' => $resis->map(function ($row) {
                $requiredQty = (int) $row->items->sum('required_qty');
                $scannedQty = (int) $row->items->sum('scanned_qty');
                return [
                    'id' => $row->id,
                    'resi_id' => $row->resi_id,
                    'no_resi' => $row->resi?->no_resi,
                    'id_pesanan' => $row->resi?->id_pesanan,
                    'tanggal_pesanan' => $row->resi?->tanggal_pesanan?->format('Y-m-d'),
                    'kurir_name' => $row->resi?->kurir?->name ?? '-',
                    'status' => $row->status,
                    'required_qty' => $requiredQty,
                    'scanned_qty' => $scannedQty,
                    'progress' => $requiredQty > 0 ? (int) floor(min(100, ($scannedQty / $requiredQty) * 100)) : 0,
                    'items' => $row->items->map(fn ($item) => [
                        'sku' => $item->sku,
                        'name' => $item->item?->name ?? '-',
                        'required_qty' => (int) $item->required_qty,
                        'scanned_qty' => (int) $item->scanned_qty,
                    ])->values(),
                ];
            })->values(),
        ];
    }

    private function ensurePickingListCapacity(string $date, string $sku, int $requiredQty): void
    {
        $row = PickingList::where('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            throw ValidationException::withMessages([
                'code' => "SKU {$sku} tidak ada di picking list tanggal {$date}.",
            ]);
        }

        if ((int) $row->remaining_qty < $requiredQty) {
            throw ValidationException::withMessages([
                'qty' => "Qty scan melebihi sisa picking list. SKU {$sku} tersisa {$row->remaining_qty}, diminta {$requiredQty}.",
            ]);
        }
    }

    private function adjustPickingRemaining(string $date, string $sku, int $deltaPicked): void
    {
        $row = PickingList::where('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            $this->adjustPickingException($date, $sku, $deltaPicked);
            return;
        }

        $remaining = (int) $row->remaining_qty;
        if ($remaining >= $deltaPicked) {
            $row->remaining_qty = $remaining - $deltaPicked;
            $row->save();
            return;
        }

        $overflow = $deltaPicked - max(0, $remaining);
        $row->remaining_qty = 0;
        $row->save();

        if ($overflow > 0) {
            $this->adjustPickingException($date, $sku, $overflow);
        }
    }

    private function adjustPickingException(string $date, string $sku, int $deltaPicked): void
    {
        $exception = PickingListException::where('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if ($exception) {
            $exception->qty += $deltaPicked;
            $exception->save();
            return;
        }

        PickingListException::create([
            'list_date' => $date,
            'sku' => $sku,
            'qty' => $deltaPicked,
        ]);
    }
}
