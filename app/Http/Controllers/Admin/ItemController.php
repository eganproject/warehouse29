<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemStock;
use App\Imports\ItemsImport;
use App\Support\StockService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    protected ?int $defaultCategoryId = null;
    public function index()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        return view('admin.masterdata.items.index', compact('categories'));
    }

    public function data(Request $request)
    {
        $query = Item::with('category')->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $catFilter = $request->input('category_id');
        if ($catFilter !== null && $catFilter !== '') {
            if ((int)$catFilter === 0) {
                $query->where('category_id', 0);
            } else {
                $query->where('category_id', (int)$catFilter);
            }
        }

        $recordsTotal = Item::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($i) {
            return [
                'id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'category' => $i->category?->name ?? '-',
                'category_id' => $i->category_id,
                'address' => $i->address ?? '',
                'description' => $i->description ?? '',
                'safety_stock' => (int) ($i->safety_stock ?? 0),
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
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100', 'unique:items,sku'],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', 'min:0', function($attr, $value, $fail) {
                if ((int)$value === 0) return;
                if (!Category::where('id', $value)->exists()) {
                    $fail('Kategori tidak valid');
                }
            }],
            'address' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        $catId = $request->input('category_id');
        $validated['category_id'] = ($catId === null || (int)$catId === 0) ? 0 : $catId;
        if (array_key_exists('safety_stock', $validated)) {
            $validated['safety_stock'] = max(0, (int) $validated['safety_stock']);
        }

        DB::beginTransaction();
        try {
            $item = Item::create($validated);
            ItemStock::firstOrCreate(['item_id' => $item->id], ['stock' => 0]);
            DB::commit();

            return response()->json([
                'message' => 'Item berhasil dibuat',
                'item' => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'category_id' => $item->category_id,
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100', Rule::unique('items', 'sku')->ignore($item->id)],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', 'min:0', function($attr, $value, $fail) {
                if ((int)$value === 0) return;
                if (!Category::where('id', $value)->exists()) {
                    $fail('Kategori tidak valid');
                }
            }],
            'address' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        $catId = $request->input('category_id');
        $validated['category_id'] = ($catId === null || (int)$catId === 0) ? 0 : $catId;
        if (array_key_exists('safety_stock', $validated)) {
            $validated['safety_stock'] = max(0, (int) $validated['safety_stock']);
        }

        DB::beginTransaction();
        try {
            $item->update($validated);
            DB::commit();

            return response()->json([
                'message' => 'Item berhasil diperbarui',
                'item' => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'category_id' => $item->category_id,
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Item $item)
    {
        DB::beginTransaction();
        try {
            $item->delete();
            DB::commit();
            return response()->json(['message' => 'Item berhasil dihapus']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $created = 0;
        $updated = 0;
        DB::beginTransaction();
        try {
            $import = new ItemsImport();
            Excel::import($import, $request->file('file'));
            $created = $import->created;
            $updated = $import->updated;

            $initialStocks = $import->initialStocks ?? [];
            if (!empty($initialStocks)) {
                $code = 'INB-OPN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
                $transactedAt = now();

                $tx = InboundTransaction::create([
                    'code' => $code,
                    'type' => 'opening',
                    'ref_no' => null,
                    'note' => 'Saldo awal dari import items',
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                ]);

                foreach ($initialStocks as $itemId => $qty) {
                    $qty = (int) $qty;
                    if ($qty <= 0) {
                        continue;
                    }
                    InboundItem::create([
                        'inbound_transaction_id' => $tx->id,
                        'item_id' => $itemId,
                        'qty' => $qty,
                        'note' => 'Saldo awal import',
                    ]);

                    StockService::mutate([
                        'item_id' => $itemId,
                        'direction' => 'in',
                        'qty' => $qty,
                        'source_type' => 'inbound',
                        'source_subtype' => 'opening',
                        'source_id' => $tx->id,
                        'source_code' => $tx->code,
                        'note' => 'Saldo awal import',
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
            return response()->json(['message' => 'Gagal import: '.$e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Import selesai',
            'created' => $created,
            'updated' => $updated,
        ]);
    }

    protected function findOrCreateCategory(string $name, int $parentId = 0): ?Category
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }
        $normalized = mb_strtolower($trimmed);
        $category = Category::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($category) {
            if ($parentId !== 0 && $category->parent_id !== $parentId) {
                $category->parent_id = $parentId;
                $category->save();
            }
            return $category;
        }
        return Category::create([
            'name' => $trimmed,
            'parent_id' => $parentId,
        ]);
    }

    protected function getDefaultCategoryId(): int
    {
        if ($this->defaultCategoryId !== null) {
            return $this->defaultCategoryId;
        }
        $default = Category::firstOrCreate(
            ['name' => 'Tanpa Kategori'],
            ['parent_id' => 0]
        );
        $this->defaultCategoryId = $default->id;
        return $this->defaultCategoryId;
    }
}
