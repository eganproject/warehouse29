<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BundleService
{
    /**
     * Compute the virtual stock for a bundle item.
     * Virtual stock = min over all components of floor(component_stock / component_qty_per_bundle).
     * Returns 0 if the bundle has no components or any component has no stock row.
     */
    public static function getVirtualStock(int $bundleItemId): int
    {
        $components = ItemBundle::where('bundle_item_id', $bundleItemId)->get();

        if ($components->isEmpty()) {
            return 0;
        }

        $stockByItemId = ItemStock::whereIn('item_id', $components->pluck('component_item_id'))
            ->pluck('stock', 'item_id');

        $min = PHP_INT_MAX;
        foreach ($components as $component) {
            $componentStock = (int) ($stockByItemId[$component->component_item_id] ?? 0);
            $perBundle = max(1, (int) $component->qty);
            $available = (int) floor($componentStock / $perBundle);
            if ($available < $min) {
                $min = $available;
            }
        }

        return $min === PHP_INT_MAX ? 0 : max(0, $min);
    }

    /**
     * Compute virtual stock for multiple bundle item IDs at once.
     * Returns [item_id => virtual_stock].
     */
    public static function getVirtualStockBatch(array $bundleItemIds): array
    {
        if (empty($bundleItemIds)) {
            return [];
        }

        $components = ItemBundle::whereIn('bundle_item_id', $bundleItemIds)->get();
        if ($components->isEmpty()) {
            return array_fill_keys($bundleItemIds, 0);
        }

        $componentItemIds = $components->pluck('component_item_id')->unique()->values()->all();
        $stockByItemId = ItemStock::whereIn('item_id', $componentItemIds)
            ->pluck('stock', 'item_id');

        $result = array_fill_keys($bundleItemIds, PHP_INT_MAX);

        foreach ($components as $component) {
            $bundleId = $component->bundle_item_id;
            $componentStock = (int) ($stockByItemId[$component->component_item_id] ?? 0);
            $perBundle = max(1, (int) $component->qty);
            $available = (int) floor($componentStock / $perBundle);

            if ($available < $result[$bundleId]) {
                $result[$bundleId] = $available;
            }
        }

        foreach ($result as $bundleId => $val) {
            $result[$bundleId] = $val === PHP_INT_MAX ? 0 : max(0, $val);
        }

        return $result;
    }

    /**
     * Validate that sufficient virtual stock exists for a bundle scan.
     * Throws ValidationException if insufficient.
     */
    public static function assertVirtualStockSufficient(int $bundleItemId, int $qty): void
    {
        $virtual = self::getVirtualStock($bundleItemId);
        if ($virtual < $qty) {
            throw ValidationException::withMessages([
                'qty' => "Stok virtual bundle tidak mencukupi (tersedia: {$virtual}, diminta: {$qty}).",
            ]);
        }
    }

    public static function isBundle(int $itemId): bool
    {
        return $itemId > 0 && (bool) DB::table('items')->where('id', $itemId)->value('is_bundle');
    }

    /**
     * Item bundle tidak punya stok fisik, sehingga tidak boleh dipakai pada transaksi
     * yang langsung mengubah stok fisik (penerimaan, penyesuaian, opname, barang rusak, dst).
     */
    public static function assertNotBundle(iterable $itemIds, string $context, string $field = 'items'): void
    {
        $ids = collect($itemIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $bundleSkus = Item::whereIn('id', $ids)->where('is_bundle', true)->orderBy('sku')->pluck('sku');
        if ($bundleSkus->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'SKU '.$bundleSkus->implode(', ').' adalah item bundle (stok virtual) dan tidak dapat digunakan pada '
                .$context.'. Gunakan SKU komponennya.',
        ]);
    }

    /**
     * Komponen untuk beberapa bundle sekaligus.
     *
     * @return array<int, \Illuminate\Support\Collection<int, ItemBundle>>  [bundle_item_id => components]
     */
    public static function componentsFor(array $bundleItemIds): array
    {
        $bundleItemIds = array_values(array_unique(array_filter(array_map('intval', $bundleItemIds))));
        if (empty($bundleItemIds)) {
            return [];
        }

        $grouped = ItemBundle::with('componentItem:id,sku,name,uom')
            ->whereIn('bundle_item_id', $bundleItemIds)
            ->orderBy('id')
            ->get()
            ->groupBy('bundle_item_id');

        $result = [];
        foreach ($bundleItemIds as $bundleId) {
            $result[$bundleId] = $grouped->get($bundleId, collect())->values();
        }

        return $result;
    }

    /**
     * Ringkasan isi bundle untuk ditampilkan ke operator, mis. "TRIP1 x10 + TRIP2 x2".
     *
     * @return array<int, array{label: string, components: array<int, array{sku: string, name: string, qty: int}>}>
     */
    public static function compositionFor(array $bundleItemIds): array
    {
        $result = [];
        foreach (self::componentsFor($bundleItemIds) as $bundleId => $components) {
            $rows = $components->map(fn (ItemBundle $component) => [
                'sku' => (string) ($component->componentItem?->sku ?? ''),
                'name' => (string) ($component->componentItem?->name ?? ''),
                'qty' => max(1, (int) $component->qty),
            ])->values()->all();

            $result[$bundleId] = [
                'label' => collect($rows)->map(fn ($row) => "{$row['sku']} x{$row['qty']}")->implode(' + '),
                'components' => $rows,
            ];
        }

        return $result;
    }

    /**
     * Pecah baris yang berisi item bundle menjadi baris komponennya.
     * Setiap kolom di $qtyKeys dikalikan qty komponen per bundle. Baris dengan item_id
     * yang sama digabung (kolom qty dijumlahkan, kolom lain memakai nilai pertama).
     */
    public static function explodeLines(array $lines, array $qtyKeys): array
    {
        $itemIds = collect($lines)->pluck('item_id')->map(fn ($id) => (int) $id)->filter()->unique()->all();
        $bundleIds = Item::whereIn('id', $itemIds)->where('is_bundle', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $componentsByBundle = self::componentsFor($bundleIds);

        $merged = [];
        foreach ($lines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $expanded = [$line];

            if (isset($componentsByBundle[$itemId])) {
                $components = $componentsByBundle[$itemId];
                if ($components->isEmpty()) {
                    $sku = Item::whereKey($itemId)->value('sku') ?? "item {$itemId}";
                    throw ValidationException::withMessages([
                        'items' => "Bundle {$sku} tidak memiliki komponen. Hubungi administrator.",
                    ]);
                }

                $expanded = $components->map(function (ItemBundle $component) use ($line, $qtyKeys) {
                    $row = $line;
                    $row['item_id'] = (int) $component->component_item_id;
                    foreach ($qtyKeys as $key) {
                        if (array_key_exists($key, $row) && $row[$key] !== null) {
                            $row[$key] = (int) $row[$key] * max(1, (int) $component->qty);
                        }
                    }

                    return $row;
                })->all();
            }

            foreach ($expanded as $row) {
                $key = (int) $row['item_id'];
                if (!isset($merged[$key])) {
                    $merged[$key] = $row;
                    continue;
                }
                foreach ($qtyKeys as $qtyKey) {
                    if (array_key_exists($qtyKey, $row) && $row[$qtyKey] !== null) {
                        $merged[$key][$qtyKey] = (int) ($merged[$key][$qtyKey] ?? 0) + (int) $row[$qtyKey];
                    }
                }
            }
        }

        return array_values($merged);
    }

    /**
     * Mutasi stok yang otomatis diterjemahkan ke komponen bila item adalah bundle.
     * Komponen pertama memakai idempotency key asli, komponen berikutnya memakai key turunan
     * (pola yang sama dengan QC scan dan outbound).
     */
    public static function mutateStock(array $payload): void
    {
        $itemId = (int) ($payload['item_id'] ?? 0);
        if (!self::isBundle($itemId)) {
            StockService::mutate($payload);

            return;
        }

        $components = ItemBundle::where('bundle_item_id', $itemId)->orderBy('id')->get();
        if ($components->isEmpty()) {
            $sku = Item::whereKey($itemId)->value('sku') ?? "item {$itemId}";
            throw ValidationException::withMessages([
                'items' => "Bundle {$sku} tidak memiliki komponen. Hubungi administrator.",
            ]);
        }

        $baseKey = $payload['idempotency_key'] ?? null;
        $bundleQty = (int) ($payload['qty'] ?? 0);
        foreach ($components->values() as $index => $component) {
            StockService::mutate([
                ...$payload,
                'item_id' => (int) $component->component_item_id,
                'qty' => $bundleQty * max(1, (int) $component->qty),
                'idempotency_key' => $index === 0 || !$baseKey
                    ? $baseKey
                    : StockService::idempotencyKey([$baseKey, 'comp', $component->component_item_id]),
            ]);
        }
    }
}
