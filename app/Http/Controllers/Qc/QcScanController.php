<?php

namespace App\Http\Controllers\Qc;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\PackerScanException;
use App\Models\QcScanResi;
use App\Models\QcScanResiBundleComponent;
use App\Models\QcScanResiItem;
use App\Models\QcTransitItem;
use App\Models\Resi;
use App\Models\StockMutation;
use App\Support\BundleService;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QcScanController extends Controller
{
    public function current()
    {
        return response()->json([
            'session' => $this->serializeDailyScanSummary(),
        ]);
    }

    public function start()
    {
        return response()->json([
            'session' => $this->serializeDailyScanSummary(),
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

        $skuBuckets = $this->buildResiSkuBuckets($resi);
        $skuTotals = $skuBuckets['required'];
        if (empty($skuTotals) && empty($skuBuckets['excluded'])) {
            return response()->json(['message' => 'Resi tidak memiliki detail SKU valid.'], 422);
        }

        $alreadyScanned = false;
        $isComplete = false;
        $qcResi = QcScanResi::where('resi_id', $resi->id)
            ->with(['scanner'])
            ->first();
        if ($qcResi) {
            if ((int) $qcResi->scanned_by !== (int) auth()->id()) {
                $scannerName = $qcResi->scanner?->name ?? 'petugas lain';
                return response()->json([
                    'message' => "Resi ini sedang/ sudah tercatat QC oleh {$scannerName}.",
                ], 422);
            }

            $alreadyScanned = true;
            $isComplete = ($qcResi->status === 'completed');
        }

        $items = $this->buildChecklist($skuTotals, $qcResi);

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

        $qcResi = DB::transaction(function () use ($validated) {
            $resi = Resi::with('details')->lockForUpdate()->findOrFail((int) $validated['resi_id']);
            if (($resi->status ?? 'active') === 'canceled') {
                throw ValidationException::withMessages(['resi' => 'Resi sudah dibatalkan, tidak bisa di-QC.']);
            }

            return $this->ensureQcResi($resi);
        });

        return response()->json([
            'message' => 'Resi tercatat untuk QC.',
            'status' => $qcResi->status,
            'session' => $this->serializeDailyScanSummary(),
        ]);
    }

    public function scanItem(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'qty' => ['nullable', 'integer', 'min:1'],
            'resi_id' => ['required', 'integer', 'exists:resis,id'],
            'request_id' => ['nullable', 'string', 'max:120'],
        ]);

        $code = trim((string) $validated['code']);
        $qty = (int) ($validated['qty'] ?? 1);
        $idempotencyKey = $this->requestIdempotencyKey('qc.scan-item', $validated['request_id'] ?? null);

        try {
            $result = DB::transaction(function () use ($validated, $code, $qty, $idempotencyKey) {
                // Idempotency: a retried request whose stock mutation already exists is a no-op.
                if ($idempotencyKey && StockMutation::where('idempotency_key', $idempotencyKey)->lockForUpdate()->exists()) {
                    $resi = Resi::with('details')->findOrFail((int) $validated['resi_id']);
                    $qcResi = QcScanResi::where('resi_id', $resi->id)->first();

                    return [
                        'summary' => $this->serializeDailyScanSummary(),
                        'items' => $this->buildChecklist($this->buildResiSkuTotals($resi), $qcResi),
                        'scan' => null,
                    ];
                }

                $item = Item::active()->where('sku', $code)->lockForUpdate()->first();
                if (!$item) {
                    throw ValidationException::withMessages(['code' => 'SKU tidak ditemukan pada master item.']);
                }

                // QC hanya menerima barang fisik. SKU bundle tidak punya barcode fisik sendiri.
                if ($item->is_bundle) {
                    $label = BundleService::compositionFor([$item->id])[$item->id]['label'] ?? '';
                    throw ValidationException::withMessages([
                        'code' => "SKU {$item->sku} adalah item bundle dan tidak bisa discan langsung."
                            .($label !== '' ? " Scan barang fisiknya: {$label} per bundle." : ' Scan barang fisik komponennya.'),
                    ]);
                }

                $resi = Resi::with('details')->lockForUpdate()->findOrFail((int) $validated['resi_id']);
                if (($resi->status ?? 'active') === 'canceled') {
                    throw ValidationException::withMessages(['resi' => 'Resi sudah dibatalkan, tidak bisa di-QC.']);
                }

                $qcResi = $this->ensureQcResi($resi);
                if ($qcResi->status === 'completed') {
                    throw ValidationException::withMessages(['resi' => 'Resi sudah selesai di-QC.']);
                }

                $scanAt = now();
                $date = $qcResi->scanned_at?->toDateString() ?? $scanAt->toDateString();

                // 1) Baris SKU langsung pada resi (item biasa).
                $ledger = QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)
                    ->where('sku', $item->sku)
                    ->lockForUpdate()
                    ->first();
                $directRemaining = $ledger ? max(0, (int) $ledger->required_qty - (int) $ledger->scanned_qty) : 0;

                if ($ledger && $qty <= $directRemaining) {
                    $this->scanDirectItem($item, $qty, $ledger, $qcResi, $resi, $date, $scanAt, $idempotencyKey);
                    $scan = ['target' => 'item', 'sku' => $item->sku];
                } else {
                    // 2) Komponen dari bundle yang ada pada resi.
                    $target = $this->findBundleTarget($qcResi, $item, $qty);
                    if (!$target) {
                        $this->throwScanNotAccepted($qcResi, $item, $qty, $ledger, $directRemaining);
                    }

                    $scan = $this->scanBundleComponent(
                        $item,
                        $qty,
                        $target['ledger'],
                        $target['component'],
                        $qcResi,
                        $resi,
                        $date,
                        $scanAt,
                        $idempotencyKey
                    );
                }

                $this->markCompletedIfReady($qcResi);

                return [
                    'summary' => $this->serializeDailyScanSummary(),
                    'items' => $this->buildChecklist($this->buildResiSkuTotals($resi), $qcResi),
                    'scan' => $scan,
                ];
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
            'session' => $result['summary'],
            'items' => $result['items'],
            'scan' => $result['scan'],
        ]);
    }

    private function scanDirectItem(
        Item $item,
        int $qty,
        QcScanResiItem $ledger,
        QcScanResi $qcResi,
        Resi $resi,
        string $date,
        \DateTimeInterface $scanAt,
        ?string $idempotencyKey
    ): void {
        $this->ensurePickingListCapacity($date, $item->sku, $qty);

        $ledger->scanned_qty = (int) $ledger->scanned_qty + $qty;
        $ledger->item_id = $item->id;
        $ledger->save();

        $this->addQcTransit($item->id, $date, $qty, $scanAt);

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
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->adjustPickingRemaining($date, $item->sku, $qty);
    }

    /**
     * Scan komponen fisik untuk baris bundle. Stok komponen dipotong saat discan;
     * baris bundle (ledger, transit, picking list) baru bertambah ketika satu set bundle lengkap.
     */
    private function scanBundleComponent(
        Item $item,
        int $qty,
        QcScanResiItem $ledger,
        QcScanResiBundleComponent $component,
        QcScanResi $qcResi,
        Resi $resi,
        string $date,
        \DateTimeInterface $scanAt,
        ?string $idempotencyKey
    ): array {
        $components = QcScanResiBundleComponent::where('qc_scan_resi_item_id', $ledger->id)
            ->lockForUpdate()
            ->get();

        $completed = PHP_INT_MAX;
        foreach ($components as $row) {
            $scanned = (int) $row->scanned_qty + ((int) $row->id === (int) $component->id ? $qty : 0);
            $completed = min($completed, intdiv($scanned, max(1, (int) $row->qty_per_bundle)));
        }
        $completed = min((int) $ledger->required_qty, $completed === PHP_INT_MAX ? 0 : $completed);
        $newlyCompleted = max(0, $completed - (int) $ledger->scanned_qty);

        // Picking list berisi barang fisik: komponen memotong picking list SKU-nya sendiri
        // di setiap scan, sama seperti item biasa.
        $this->ensurePickingListCapacity($date, $item->sku, $qty);

        $component->scanned_qty = (int) $component->scanned_qty + $qty;
        $component->save();

        StockService::mutate([
            'item_id' => $item->id,
            'direction' => 'out',
            'qty' => $qty,
            'source_type' => 'qc_resi',
            'source_subtype' => 'scan_bundle',
            'source_id' => $qcResi->id,
            'source_code' => $resi->no_resi ?: $resi->id_pesanan,
            'note' => "QC scan komponen {$item->sku} untuk bundle {$ledger->sku}",
            'occurred_at' => $scanAt,
            'created_by' => auth()->id(),
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->adjustPickingRemaining($date, $item->sku, $qty);

        if ($newlyCompleted > 0) {
            $ledger->scanned_qty = $completed;
            $ledger->save();

            // Transit tetap dicatat per SKU bundle pada resi karena dipakai scan out.
            $this->addQcTransit((int) $ledger->item_id, $date, $newlyCompleted, $scanAt);
        }

        return [
            'target' => 'bundle',
            'sku' => $item->sku,
            'bundle_sku' => $ledger->sku,
            'bundle_completed' => $newlyCompleted,
            'bundle_scanned_qty' => (int) $ledger->scanned_qty,
            'bundle_required_qty' => (int) $ledger->required_qty,
            'component_scanned_qty' => (int) $component->scanned_qty,
            'component_required_qty' => (int) $ledger->required_qty * (int) $component->qty_per_bundle,
        ];
    }

    /**
     * Cari baris bundle pada resi yang masih membutuhkan komponen ini sebanyak $qty.
     *
     * @return array{ledger: QcScanResiItem, component: QcScanResiBundleComponent}|null
     */
    private function findBundleTarget(QcScanResi $qcResi, Item $item, int $qty): ?array
    {
        $candidates = QcScanResiBundleComponent::query()
            ->join('qc_scan_resi_items as qri', 'qri.id', '=', 'qc_scan_resi_bundle_components.qc_scan_resi_item_id')
            ->where('qri.qc_scan_resi_id', $qcResi->id)
            ->where('qc_scan_resi_bundle_components.component_item_id', $item->id)
            ->orderBy('qri.id')
            ->select('qc_scan_resi_bundle_components.*')
            ->lockForUpdate()
            ->get();

        foreach ($candidates as $component) {
            $ledger = QcScanResiItem::whereKey($component->qc_scan_resi_item_id)->lockForUpdate()->first();
            if (!$ledger || $this->isPackerException($ledger->sku)) {
                continue;
            }

            $required = (int) $ledger->required_qty * (int) $component->qty_per_bundle;
            if ($qty <= $required - (int) $component->scanned_qty) {
                return ['ledger' => $ledger, 'component' => $component];
            }
        }

        return null;
    }

    private function throwScanNotAccepted(
        QcScanResi $qcResi,
        Item $item,
        int $qty,
        ?QcScanResiItem $ledger,
        int $directRemaining
    ): never {
        $bundleRows = QcScanResiBundleComponent::query()
            ->join('qc_scan_resi_items as qri', 'qri.id', '=', 'qc_scan_resi_bundle_components.qc_scan_resi_item_id')
            ->where('qri.qc_scan_resi_id', $qcResi->id)
            ->where('qc_scan_resi_bundle_components.component_item_id', $item->id)
            ->get([
                'qri.sku as bundle_sku',
                'qri.required_qty',
                'qc_scan_resi_bundle_components.qty_per_bundle',
                'qc_scan_resi_bundle_components.scanned_qty',
            ]);

        $bundleRemaining = (int) $bundleRows->sum(
            fn ($row) => max(0, (int) $row->required_qty * (int) $row->qty_per_bundle - (int) $row->scanned_qty)
        );

        if (!$ledger && $bundleRows->isEmpty()) {
            throw ValidationException::withMessages([
                'code' => "SKU {$item->sku} tidak ditemukan dalam resi tersebut.",
            ]);
        }

        $totalRemaining = $directRemaining + $bundleRemaining;
        if ($totalRemaining <= 0) {
            throw ValidationException::withMessages([
                'qty' => "SKU {$item->sku} sudah lengkap untuk resi ini.",
            ]);
        }

        $parts = [];
        if ($ledger) {
            $parts[] = "langsung {$directRemaining}";
        }
        foreach ($bundleRows as $row) {
            $remaining = max(0, (int) $row->required_qty * (int) $row->qty_per_bundle - (int) $row->scanned_qty);
            $parts[] = "bundle {$row->bundle_sku} {$remaining}";
        }

        throw ValidationException::withMessages([
            'qty' => "Qty scan ({$qty}) melebihi sisa resi untuk SKU {$item->sku} (".implode(', ', $parts).'). '
                .'Scan dengan qty lebih kecil.',
        ]);
    }

    private function addQcTransit(int $itemId, string $date, int $qty, \DateTimeInterface $scanAt): void
    {
        $transit = QcTransitItem::where('item_id', $itemId)
            ->whereDate('transit_date', $date)
            ->lockForUpdate()
            ->first();

        if ($transit) {
            $transit->qty += $qty;
            $transit->remaining_qty += $qty;
            $transit->last_qc_at = $scanAt;
            $transit->save();

            return;
        }

        QcTransitItem::create([
            'item_id' => $itemId,
            'transit_date' => $date,
            'qty' => $qty,
            'remaining_qty' => $qty,
            'last_qc_at' => $scanAt,
        ]);
    }

    private function requestIdempotencyKey(string $action, ?string $requestId): ?string
    {
        $requestId = trim((string) ($requestId ?? ''));
        if ($requestId === '') {
            return null;
        }

        return StockService::idempotencyKey(['request', $action, auth()->id(), $requestId]);
    }

    public function searchItems(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        // SKU bundle tidak bisa discan di QC, jadi tidak ditawarkan.
        $query = Item::active()->where('is_bundle', false);
        if ($search !== '') {
            $query->where('sku', 'like', "%{$search}%");
        }

        return response()->json([
            'items' => $query->orderBy('sku')->get(['id', 'sku', 'name', 'address']),
        ]);
    }

    private function ensureQcResi(Resi $resi): QcScanResi
    {
        $qcResi = QcScanResi::where('resi_id', $resi->id)
            ->lockForUpdate()
            ->first();

        if (!$qcResi) {
            $qcResi = QcScanResi::create([
                'resi_id' => $resi->id,
                'status' => 'in_progress',
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
            ]);
        } elseif ((int) $qcResi->scanned_by !== (int) auth()->id()) {
            $qcResi->loadMissing('scanner:id,name');
            $scannerName = $qcResi->scanner?->name ?? 'petugas lain';
            throw ValidationException::withMessages([
                'resi' => "Resi ini sedang/ sudah tercatat QC oleh {$scannerName}.",
            ]);
        }

        $skuBuckets = $this->buildResiSkuBuckets($resi);
        $skuTotals = $skuBuckets['required'];
        if (empty($skuTotals)) {
            if (!empty($skuBuckets['excluded'])) {
                QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)
                    ->where('scanned_qty', 0)
                    ->whereDoesntHave('bundleComponents', fn ($q) => $q->where('scanned_qty', '>', 0))
                    ->delete();

                $qcResi->status = 'completed';
                $qcResi->completed_at = $qcResi->completed_at ?: now();
                $qcResi->completed_by = $qcResi->completed_by ?: auth()->id();
                $qcResi->save();

                return $qcResi->fresh(['resi', 'items.item']);
            }

            throw ValidationException::withMessages(['resi' => 'Resi tidak memiliki detail SKU valid.']);
        }

        $itemIdsBySku = Item::active()->whereIn('sku', array_keys($skuTotals))->pluck('id', 'sku')->all();
        QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)
            ->whereNotIn('sku', array_keys($skuTotals))
            ->where('scanned_qty', 0)
            // Komponen bundle yang sudah discan sudah memotong stok; jangan hilangkan jejaknya.
            ->whereDoesntHave('bundleComponents', fn ($q) => $q->where('scanned_qty', '>', 0))
            ->delete();

        foreach ($skuTotals as $sku => $requiredQty) {
            QcScanResiItem::updateOrCreate(
                ['qc_scan_resi_id' => $qcResi->id, 'sku' => $sku],
                ['item_id' => $itemIdsBySku[$sku] ?? null, 'required_qty' => $requiredQty]
            );
        }

        $this->ensureBundleComponentRows($qcResi);
        $this->markCompletedIfReady($qcResi);

        return $qcResi->fresh(['resi', 'items.item']);
    }

    /**
     * Snapshot komposisi bundle untuk setiap baris bundle pada QC resi, sehingga perubahan
     * master bundle di tengah proses QC tidak mengubah kebutuhan scan resi yang sedang berjalan.
     * Baris bundle lama (sebelum fitur scan komponen) yang sudah ter-scan dianggap komponennya
     * sudah lengkap sesuai jumlah bundle yang ter-scan, karena stok komponennya sudah dipotong.
     */
    private function ensureBundleComponentRows(QcScanResi $qcResi): void
    {
        $ledgers = QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)
            ->whereNotNull('item_id')
            ->lockForUpdate()
            ->get();
        if ($ledgers->isEmpty()) {
            return;
        }

        $bundleIds = Item::whereIn('id', $ledgers->pluck('item_id'))
            ->where('is_bundle', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if (empty($bundleIds)) {
            return;
        }

        $initialized = QcScanResiBundleComponent::whereIn('qc_scan_resi_item_id', $ledgers->pluck('id'))
            ->pluck('qc_scan_resi_item_id')
            ->map(fn ($id) => (int) $id)
            ->flip();
        $componentsByBundle = BundleService::componentsFor($bundleIds);

        foreach ($ledgers as $ledger) {
            $bundleId = (int) $ledger->item_id;
            if (!isset($componentsByBundle[$bundleId]) || isset($initialized[(int) $ledger->id])) {
                continue;
            }

            foreach ($componentsByBundle[$bundleId] as $component) {
                $perBundle = max(1, (int) $component->qty);
                QcScanResiBundleComponent::create([
                    'qc_scan_resi_item_id' => $ledger->id,
                    'component_item_id' => $component->component_item_id,
                    'component_sku' => (string) ($component->componentItem?->sku ?? ''),
                    'qty_per_bundle' => $perBundle,
                    'scanned_qty' => (int) $ledger->scanned_qty * $perBundle,
                ]);
            }
        }
    }

    /**
     * Checklist resi untuk layar QC. Baris bundle membawa daftar komponen yang harus discan.
     */
    private function buildChecklist(array $skuTotals, ?QcScanResi $qcResi): array
    {
        if (empty($skuTotals)) {
            return [];
        }

        $ledgers = $qcResi
            ? QcScanResiItem::with('bundleComponents.componentItem:id,sku,name')
                ->where('qc_scan_resi_id', $qcResi->id)
                ->get()
                ->keyBy(fn ($row) => strtolower((string) $row->sku))
            : collect();

        $items = Item::active()
            ->whereIn('sku', array_keys($skuTotals))
            ->get(['id', 'sku', 'name', 'is_bundle'])
            ->keyBy(fn ($row) => strtolower((string) $row->sku));
        $composition = BundleService::compositionFor(
            $items->where('is_bundle', true)->pluck('id')->all()
        );

        $rows = [];
        foreach ($skuTotals as $sku => $qty) {
            $key = strtolower((string) $sku);
            $ledger = $ledgers->get($key);
            $item = $items->get($key);
            $scanned = min((int) ($ledger?->scanned_qty ?? 0), (int) $qty);
            $rows[] = [
                'sku' => $sku,
                'name' => $item?->name,
                'qty' => (int) $qty,
                'scanned_qty' => $scanned,
                ...$this->bundlePayload($item, $ledger, (int) $qty, $scanned, $composition),
            ];
        }

        return $rows;
    }

    private function bundlePayload(?Item $item, ?QcScanResiItem $ledger, int $requiredQty, int $scannedQty, array $composition): array
    {
        if (!$item || !$item->is_bundle) {
            return ['is_bundle' => false, 'bundle_label' => null, 'components' => []];
        }

        $progress = $ledger?->relationLoaded('bundleComponents') ? $ledger->bundleComponents : collect();
        if ($progress->isNotEmpty()) {
            $components = $progress->map(function (QcScanResiBundleComponent $row) use ($requiredQty) {
                $perBundle = max(1, (int) $row->qty_per_bundle);
                $required = $requiredQty * $perBundle;

                return [
                    'sku' => $row->component_sku ?: ($row->componentItem?->sku ?? ''),
                    'name' => $row->componentItem?->name,
                    'qty_per_bundle' => $perBundle,
                    'required_qty' => $required,
                    'scanned_qty' => min((int) $row->scanned_qty, $required),
                ];
            })->values()->all();
        } else {
            $components = collect($composition[$item->id]['components'] ?? [])
                ->map(fn ($row) => [
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'qty_per_bundle' => $row['qty'],
                    'required_qty' => $requiredQty * $row['qty'],
                    'scanned_qty' => $scannedQty * $row['qty'],
                ])->values()->all();
        }

        return [
            'is_bundle' => true,
            'bundle_label' => collect($components)->map(fn ($row) => "{$row['sku']} x{$row['qty_per_bundle']}")->implode(' + '),
            'components' => $components,
        ];
    }

    private function isPackerException(string $sku): bool
    {
        return isset($this->packerScanExceptionLookup()[strtolower(trim($sku))]);
    }

    private function buildResiSkuTotals(Resi $resi): array
    {
        return $this->buildResiSkuBuckets($resi)['required'];
    }

    private function buildResiSkuBuckets(Resi $resi): array
    {
        $resi->loadMissing('details');
        $exceptionLookup = $this->packerScanExceptionLookup();
        $totals = [];
        $excludedTotals = [];
        foreach ($resi->details as $detail) {
            $sku = trim((string) ($detail->sku ?? ''));
            $qty = (int) ($detail->qty ?? 0);
            if ($sku !== '' && isset($exceptionLookup[strtolower($sku)])) {
                if ($qty > 0) {
                    $excludedTotals[$sku] = ($excludedTotals[$sku] ?? 0) + $qty;
                }
                continue;
            }
            if ($sku !== '' && $qty > 0) {
                $totals[$sku] = ($totals[$sku] ?? 0) + $qty;
            }
        }
        ksort($totals, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($excludedTotals, SORT_NATURAL | SORT_FLAG_CASE);
        return [
            'required' => $totals,
            'excluded' => $excludedTotals,
        ];
    }

    private function markCompletedIfReady(QcScanResi $qcResi): void
    {
        $exceptionLookup = $this->packerScanExceptionLookup();
        $items = QcScanResiItem::where('qc_scan_resi_id', $qcResi->id)
            ->lockForUpdate()
            ->get()
            ->reject(fn ($item) => isset($exceptionLookup[strtolower((string) $item->sku)]));
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

    private function serializeDailyScanSummary(): array
    {
        $exceptionLookup = $this->packerScanExceptionLookup();
        $resis = QcScanResi::with(['resi.kurir', 'items.item', 'items.bundleComponents.componentItem:id,sku,name'])
            ->where('scanned_by', auth()->id())
            ->whereDate('scanned_at', now()->toDateString())
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->get();
        $composition = BundleService::compositionFor(
            $resis->flatMap(fn ($row) => $row->items)
                ->filter(fn ($item) => (bool) $item->item?->is_bundle)
                ->pluck('item_id')
                ->unique()
                ->all()
        );

        $items = $resis
            ->flatMap(fn ($resi) => $resi->items)
            ->reject(fn ($item) => isset($exceptionLookup[strtolower((string) $item->sku)]))
            ->groupBy('sku')
            ->map(function ($rows, $sku) {
                $first = $rows->first();
                return [
                    'sku' => $sku,
                    'name' => $first?->item?->name ?? '-',
                    'qty' => (int) $rows->sum('scanned_qty'),
                    'required_qty' => (int) $rows->sum('required_qty'),
                ];
            })->values();
        $firstScanAt = $resis->min('scanned_at');
        $lastScanAt = $resis->max(function ($row) {
            return $row->completed_at ?: $row->updated_at ?: $row->scanned_at;
        });

        return [
            'id' => null,
            'code' => null,
            'status' => 'active',
            'started_at' => $firstScanAt ? Carbon::parse($firstScanAt)->format('Y-m-d H:i') : null,
            'last_scan_at' => $lastScanAt ? Carbon::parse($lastScanAt)->format('Y-m-d H:i') : null,
            'items' => $items,
            'resis' => $resis->map(function ($row) use ($exceptionLookup, $composition) {
                $items = $row->items->reject(fn ($item) => isset($exceptionLookup[strtolower((string) $item->sku)]));
                $requiredQty = (int) $items->sum('required_qty');
                $scannedQty = (int) $items->sum('scanned_qty');
                return [
                    'id' => $row->id,
                    'resi_id' => $row->resi_id,
                    'no_resi' => $row->resi?->no_resi,
                    'id_pesanan' => $row->resi?->id_pesanan,
                    'tanggal_pesanan' => $row->resi?->tanggal_pesanan?->format('Y-m-d'),
                    'kurir_name' => $row->resi?->kurir?->name ?? '-',
                    'status' => $row->status,
                    'scanned_at' => $row->scanned_at?->format('Y-m-d H:i'),
                    'completed_at' => $row->completed_at?->format('Y-m-d H:i'),
                    'required_qty' => $requiredQty,
                    'scanned_qty' => $scannedQty,
                    'progress' => $requiredQty > 0 ? (int) floor(min(100, ($scannedQty / $requiredQty) * 100)) : 0,
                    'items' => $items->map(fn ($item) => [
                        'sku' => $item->sku,
                        'name' => $item->item?->name ?? '-',
                        'required_qty' => (int) $item->required_qty,
                        'scanned_qty' => (int) $item->scanned_qty,
                        ...$this->bundlePayload(
                            $item->item,
                            $item,
                            (int) $item->required_qty,
                            min((int) $item->scanned_qty, (int) $item->required_qty),
                            $composition
                        ),
                    ])->values(),
                ];
            })->values(),
        ];
    }

    private function packerScanExceptionLookup(): array
    {
        return PackerScanException::query()
            ->pluck('sku')
            ->map(fn ($sku) => strtolower(trim((string) $sku)))
            ->filter()
            ->flip()
            ->all();
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
