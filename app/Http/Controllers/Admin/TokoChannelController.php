<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Toko;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TokoChannelController extends Controller
{
    /**
     * Konfigurasi per tab. Route {type} dibatasi ke key array ini.
     *
     * @var array<string,array{model:class-string<Model>,table:string,label:string,max:int}>
     */
    private const TYPES = [
        'toko' => ['model' => Toko::class, 'table' => 'tokos', 'label' => 'Toko', 'max' => 150],
        'channel' => ['model' => Channel::class, 'table' => 'channels', 'label' => 'Channel', 'max' => 100],
    ];

    public function index()
    {
        return view('admin.masterdata.toko-channel.index');
    }

    public function data(Request $request, string $type)
    {
        $config = self::TYPES[$type];
        $modelClass = $config['model'];

        $query = $modelClass::query()->withCount('resis')->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $recordsTotal = $modelClass::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->name,
            'resis_count' => (int) $row->resis_count,
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(Request $request, string $type)
    {
        $config = self::TYPES[$type];
        $validated = $this->validateName($request, $config);

        DB::beginTransaction();
        try {
            $row = $config['model']::create($validated);
            DB::commit();

            return response()->json([
                'message' => $config['label'].' berhasil dibuat',
                'data' => ['id' => $row->id, 'name' => $row->name],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat '.mb_strtolower($config['label']),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $type, int $id)
    {
        $config = self::TYPES[$type];
        $row = $config['model']::findOrFail($id);
        $validated = $this->validateName($request, $config, $row->id);

        DB::beginTransaction();
        try {
            $row->update($validated);
            DB::commit();

            return response()->json([
                'message' => $config['label'].' berhasil diperbarui',
                'data' => ['id' => $row->id, 'name' => $row->name],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui '.mb_strtolower($config['label']),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $type, int $id)
    {
        $config = self::TYPES[$type];
        $row = $config['model']::withCount('resis')->findOrFail($id);

        // Cegah resi kehilangan informasi toko/channel.
        if ($row->resis_count > 0) {
            return response()->json([
                'message' => $config['label'].' masih dipakai oleh '.$row->resis_count.' resi dan tidak dapat dihapus.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $row->delete();
            DB::commit();
            return response()->json(['message' => $config['label'].' berhasil dihapus']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus '.mb_strtolower($config['label']),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param array{table:string,max:int} $config
     */
    private function validateName(Request $request, array $config, ?int $ignoreId = null): array
    {
        $request->merge([
            'name' => trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('name', ''))),
        ]);

        $unique = Rule::unique($config['table'], 'name');
        if ($ignoreId) {
            $unique->ignore($ignoreId);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:'.$config['max'], $unique],
        ]);
    }
}
