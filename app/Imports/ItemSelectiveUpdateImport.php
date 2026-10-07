<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemStock;
use App\Models\UnitOfMeasure;
use App\Support\ItemBulkUpdateFields;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemSelectiveUpdateImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public int $matched = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    /** @var array<int, string> */
    private array $fields;

    public function __construct(array $fields)
    {
        $this->fields = ItemBulkUpdateFields::normalize($fields);
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->fail(2, 'File tidak berisi data item.');
        }

        $this->validateHeaders(array_keys($rows->first()->toArray()));

        $seenSkus = [];
        foreach ($rows->values() as $index => $row) {
            $rowNumber = $index + 2;
            $sku = trim((string) $row->get('sku', ''));

            if ($sku === '') {
                $this->fail($rowNumber, 'SKU wajib diisi.');
            }

            $skuKey = mb_strtolower($sku);
            if (isset($seenSkus[$skuKey])) {
                $this->fail($rowNumber, "SKU {$sku} duplikat dengan baris {$seenSkus[$skuKey]}.");
            }
            $seenSkus[$skuKey] = $rowNumber;

            $item = Item::query()->where('sku', $sku)->first();
            if (! $item) {
                $this->fail($rowNumber, "SKU {$sku} tidak ditemukan. Import update tidak membuat item baru.");
            }

            $payload = [];
            $bundleData = null;

            foreach ($this->fields as $field) {
                match ($field) {
                    'name' => $payload['name'] = $this->parseName($row->get('name'), $rowNumber),
                    'uom' => $payload['uom'] = $this->parseUom($row->get('uom'), $rowNumber),
                    'category' => $payload['category_id'] = $this->parseCategory(
                        $row->get('parent_category'),
                        $row->get('category'),
                        $rowNumber
                    ),
                    'address' => $payload['address'] = $this->nullableText($row->get('address')),
                    'description' => $payload['description'] = $this->nullableText($row->get('description')),
                    'safety_stock' => $payload['safety_stock'] = $this->parseSafetyStock($row->get('safety_stock'), $rowNumber),
                    'is_active' => $payload['is_active'] = $this->parseBooleanStatus($row->get('is_active'), $rowNumber, 'status aktif'),
                    'bundle' => $bundleData = $this->parseBundle($item, $row, $rowNumber),
                    default => null,
                };
            }

            $item->fill($payload);
            $itemChanged = $item->isDirty();
            $bundleChanged = $bundleData !== null && $this->bundleHasChanges($item, $bundleData);

            if ($itemChanged) {
                $item->save();
            }
            if ($bundleData !== null) {
                $this->applyBundle($item, $bundleData, $bundleChanged);
            }

            $this->matched++;
            if ($itemChanged || $bundleChanged) {
                $this->updated++;
            } else {
                $this->unchanged++;
            }
        }
    }

    private function validateHeaders(array $headers): void
    {
        $expected = ItemBulkUpdateFields::headings($this->fields);
        $headers = array_values(array_filter($headers, static fn ($header) => trim((string) $header) !== ''));
        $missing = array_values(array_diff($expected, $headers));
        $unexpected = array_values(array_diff($headers, $expected));

        if ($missing !== [] || $unexpected !== []) {
            $parts = [];
            if ($missing !== []) {
                $parts[] = 'kolom kurang: '.implode(', ', $missing);
            }
            if ($unexpected !== []) {
                $parts[] = 'kolom tidak dipilih: '.implode(', ', $unexpected);
            }

            throw ValidationException::withMessages([
                'file' => 'Header file tidak sesuai pengaturan ('.implode('; ', $parts).'). Unduh ulang template sesuai field yang dipilih.',
            ]);
        }
    }

    private function parseName(mixed $raw, int $rowNumber): string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            $this->fail($rowNumber, 'Nama item wajib diisi.');
        }
        if (mb_strlen($value) > 150) {
            $this->fail($rowNumber, 'Nama item maksimal 150 karakter.');
        }

        return $value;
    }

    private function parseUom(mixed $raw, int $rowNumber): string
    {
        $value = mb_strtolower(trim((string) $raw));
        if ($value === '') {
            $this->fail($rowNumber, 'UOM wajib diisi.');
        }
        if (mb_strlen($value) > 30) {
            $this->fail($rowNumber, 'UOM maksimal 30 karakter.');
        }
        if (! UnitOfMeasure::query()->where('code', $value)->exists()) {
            $this->fail($rowNumber, "UOM {$value} belum terdaftar di Master Data Satuan.");
        }

        return $value;
    }

    private function parseCategory(mixed $parentRaw, mixed $categoryRaw, int $rowNumber): int
    {
        $parentName = trim((string) $parentRaw);
        $categoryName = trim((string) $categoryRaw);

        if ($categoryName === '') {
            return 0;
        }

        $parentId = 0;
        if ($parentName !== '') {
            $parent = Category::query()
                ->where('parent_id', 0)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($parentName)])
                ->first();
            if (! $parent) {
                $this->fail($rowNumber, "Parent kategori {$parentName} tidak ditemukan.");
            }
            $parentId = (int) $parent->id;
        }

        $category = Category::query()
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])
            ->first();
        if (! $category) {
            $path = $parentName !== '' ? "{$parentName} / {$categoryName}" : $categoryName;
            $this->fail($rowNumber, "Kategori {$path} tidak ditemukan di Master Data Kategori.");
        }

        return (int) $category->id;
    }

    private function parseSafetyStock(mixed $raw, int $rowNumber): int
    {
        $value = trim((string) $raw);
        if ($value === '' || ! preg_match('/^\d+$/', $value)) {
            $this->fail($rowNumber, 'Stok pengaman wajib berupa bilangan bulat minimal 0.');
        }

        if (strlen($value) > 10 || (float) $value > 4294967295) {
            $this->fail($rowNumber, 'Stok pengaman melebihi batas maksimum yang didukung.');
        }

        return (int) $value;
    }

    private function parseBooleanStatus(mixed $raw, int $rowNumber, string $label): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }

        $value = mb_strtolower(trim((string) $raw));
        if (in_array($value, ['1', 'true', 'yes', 'y', 'aktif', 'active', 'bundle'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no', 'n', 'nonaktif', 'tidak aktif', 'inactive', 'biasa', 'regular'], true)) {
            return false;
        }

        $this->fail($rowNumber, "Nilai {$label} tidak valid.");
    }

    private function parseBundle(Item $item, Collection $row, int $rowNumber): array
    {
        $isBundle = $this->parseBooleanStatus($row->get('is_bundle'), $rowNumber, 'tipe bundle');
        $components = $isBundle
            ? $this->parseBundleComponents($item, $row->get('bundle_components'), $rowNumber)
            : [];

        if ((bool) $item->is_bundle !== $isBundle) {
            $this->assertBundleToggleSafe($item, $isBundle, $rowNumber);
        }

        return [
            'is_bundle' => $isBundle,
            'components' => $components,
        ];
    }

    private function parseBundleComponents(Item $item, mixed $raw, int $rowNumber): array
    {
        $value = trim((string) $raw);
        if ($value === '') {
            $this->fail($rowNumber, 'Item bundle wajib memiliki minimal satu komponen.');
        }

        $parsed = [];
        foreach (preg_split('/\s*\|\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            if (! preg_match('/^(.+):\s*(\d+)$/', trim($entry), $matches)) {
                $this->fail($rowNumber, "Format komponen '{$entry}' tidak valid. Gunakan SKU:QTY dan pisahkan dengan tanda |.");
            }

            $sku = trim($matches[1]);
            $qty = (int) $matches[2];
            $key = mb_strtolower($sku);
            if ($sku === '' || $qty < 1) {
                $this->fail($rowNumber, "Komponen {$entry} harus memiliki qty minimal 1.");
            }
            if (isset($parsed[$key])) {
                $this->fail($rowNumber, "Komponen SKU {$sku} ditulis lebih dari satu kali.");
            }

            $parsed[$key] = ['sku' => $sku, 'qty' => $qty];
        }

        $items = Item::query()
            ->whereIn('sku', array_column($parsed, 'sku'))
            ->get()
            ->keyBy(fn (Item $component) => mb_strtolower($component->sku));

        $components = [];
        foreach ($parsed as $key => $entry) {
            /** @var Item|null $component */
            $component = $items->get($key);
            if (! $component) {
                $this->fail($rowNumber, "SKU komponen {$entry['sku']} tidak ditemukan.");
            }
            if ($component->id === $item->id) {
                $this->fail($rowNumber, 'Bundle tidak dapat menjadi komponen dirinya sendiri.');
            }
            if (! $component->is_active) {
                $this->fail($rowNumber, "Komponen {$entry['sku']} sedang nonaktif.");
            }
            if ($component->is_bundle) {
                $this->fail($rowNumber, "Komponen {$entry['sku']} tidak boleh berupa bundle lain.");
            }

            $components[] = [
                'component_item_id' => (int) $component->id,
                'qty' => $entry['qty'],
            ];
        }

        return $components;
    }

    private function bundleHasChanges(Item $item, array $bundleData): bool
    {
        if ((bool) $item->is_bundle !== $bundleData['is_bundle']) {
            return true;
        }

        $current = ItemBundle::query()
            ->where('bundle_item_id', $item->id)
            ->orderBy('component_item_id')
            ->get(['component_item_id', 'qty'])
            ->map(fn (ItemBundle $component) => [(int) $component->component_item_id, (int) $component->qty])
            ->all();
        $incoming = collect($bundleData['components'])
            ->sortBy('component_item_id')
            ->map(fn (array $component) => [(int) $component['component_item_id'], (int) $component['qty']])
            ->values()
            ->all();

        return $current !== $incoming;
    }

    private function applyBundle(Item $item, array $bundleData, bool $changed): void
    {
        if (! $changed) {
            return;
        }

        $item->is_bundle = $bundleData['is_bundle'];
        $item->save();
        ItemBundle::query()->where('bundle_item_id', $item->id)->delete();

        if ($bundleData['is_bundle']) {
            foreach ($bundleData['components'] as $component) {
                ItemBundle::query()->create([
                    'bundle_item_id' => $item->id,
                    'component_item_id' => $component['component_item_id'],
                    'qty' => $component['qty'],
                ]);
            }
            ItemStock::query()->where('item_id', $item->id)->where('stock', 0)->delete();
        } else {
            ItemStock::query()->firstOrCreate(['item_id' => $item->id], ['stock' => 0]);
        }
    }

    private function assertBundleToggleSafe(Item $item, bool $toBundle, int $rowNumber): void
    {
        if ($toBundle) {
            $hasMutations = DB::table('stock_mutations')->where('item_id', $item->id)->exists();
            $hasStock = (int) DB::table('item_stocks')->where('item_id', $item->id)->value('stock') > 0;
            $hasDamagedStock = DB::table('damaged_item_stocks')->where('item_id', $item->id)->where('stock', '>', 0)->exists()
                || DB::table('damaged_stock_mutations')->where('item_id', $item->id)->exists();
            $isUsedAsComponent = ItemBundle::query()->where('component_item_id', $item->id)->exists();
            if ($hasMutations || $hasStock || $hasDamagedStock || $isUsedAsComponent) {
                $this->fail($rowNumber, 'Item tidak dapat dijadikan bundle karena memiliki stok/riwayat stok atau dipakai sebagai komponen bundle.');
            }
        } elseif (
            DB::table('qc_transit_items')->where('item_id', $item->id)->exists()
            || DB::table('qc_scan_resi_items')->where('item_id', $item->id)->exists()
            || DB::table('outbound_items')->where('item_id', $item->id)->exists()
        ) {
            $this->fail($rowNumber, 'Item bundle tidak dapat dijadikan item biasa karena memiliki riwayat QC atau barang keluar.');
        }
    }

    private function nullableText(mixed $raw): ?string
    {
        $value = trim((string) $raw);

        return $value === '' ? null : $value;
    }

    private function fail(int $rowNumber, string $message): never
    {
        throw ValidationException::withMessages([
            'file' => "Baris {$rowNumber}: {$message}",
        ]);
    }
}
