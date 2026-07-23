<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitOfMeasureController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.unit-of-measures.index');
    }

    public function data(Request $request)
    {
        $query = UnitOfMeasure::query()->orderBy('code');
        if ($q = trim((string) $request->input('q', ''))) {
            $query->where(fn ($x) => $x->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
        }
        $total = UnitOfMeasure::count();
        $filtered = (clone $query)->count();
        $query->skip(max(0, (int) $request->input('start', 0)))->take(min(100, max(1, (int) $request->input('length', 10))));

        return response()->json([
            'draw' => (int) $request->input('draw'), 'recordsTotal' => $total,
            'recordsFiltered' => $filtered, 'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $uom = UnitOfMeasure::create($data);
        return response()->json(['message' => 'Satuan berhasil dibuat', 'data' => $uom]);
    }

    public function update(Request $request, UnitOfMeasure $unitOfMeasure)
    {
        $data = $this->validateData($request, $unitOfMeasure);
        if ($data['code'] !== $unitOfMeasure->code && Item::where('uom', $unitOfMeasure->code)->exists()) {
            return response()->json(['message' => 'Kode satuan sudah dipakai item dan tidak dapat diubah.'], 422);
        }
        $unitOfMeasure->update($data);
        return response()->json(['message' => 'Satuan berhasil diperbarui', 'data' => $unitOfMeasure]);
    }

    public function destroy(UnitOfMeasure $unitOfMeasure)
    {
        if (Item::where('uom', $unitOfMeasure->code)->exists()) {
            return response()->json(['message' => 'Satuan masih dipakai oleh item dan tidak dapat dihapus.'], 422);
        }
        $unitOfMeasure->delete();
        return response()->json(['message' => 'Satuan berhasil dihapus']);
    }

    private function validateData(Request $request, ?UnitOfMeasure $uom = null): array
    {
        $request->merge(['code' => strtolower(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[a-zA-Z0-9_-]+$/', Rule::unique('unit_of_measures', 'code')->ignore($uom?->id)],
            'name' => ['required', 'string', 'max:100'],
        ]);
        return $data;
    }
}
