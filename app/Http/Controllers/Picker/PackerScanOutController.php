<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Models\Kurir;
use App\Models\PackerScanOut;
use App\Models\PackerTransitHistory;
use App\Models\Resi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackerScanOutController extends Controller
{
    public function index()
    {
        return view('picker.scan-out', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'scan' => route('picker.scan-out.scan'),
                'history' => route('picker.scan-out.history'),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function indexV2()
    {
        return view('picker.scan-out-v2', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'scan' => route('picker.scan-out.scan'),
                'history' => route('picker.scan-out.history'),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function history()
    {
        return view('picker.scan-out-history', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'scanOut' => route('picker.scan-out.index'),
                'data' => route('picker.scan-out.history-data'),
                'logout' => route('logout'),
            ],
            'today' => now()->toDateString(),
        ]);
    }

    public function historyData(Request $request)
    {
        $query = PackerScanOut::query()
            ->with('resi')
            ->orderByDesc('scanned_at')
            ->orderByDesc('id');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('scan_code', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('id_pesanan', 'like', "%{$search}%")
                            ->orWhere('no_resi', 'like', "%{$search}%");
                    });
            });
        }

        $date = $request->input('date') ?: now()->toDateString();
        try {
            if ($date) {
                $query->whereDate('scan_date', $date);
            }
        } catch (\Throwable) {
            // ignore invalid date
        }

        $items = $query->get()->map(function ($row) {
            return [
                'id_pesanan' => $row->resi?->id_pesanan ?? '-',
                'no_resi' => $row->resi?->no_resi ?? '-',
                'scan_type' => $row->scan_type ?? '-',
                'scan_code' => $row->scan_code ?? '-',
                'scanned_at' => $row->scanned_at?->format('Y-m-d H:i') ?? '-',
            ];
        });

        return response()->json([
            'items' => $items,
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:id_pesanan,no_resi'],
            'code' => ['required', 'string'],
        ]);

        $type = $validated['type'];
        $code = trim((string) $validated['code']);
        if ($code === '') {
            return response()->json([
                'message' => 'Kode tidak boleh kosong.',
            ], 422);
        }

        $resiQuery = Resi::query();
        if ($type === 'no_resi') {
            $resiQuery->where('no_resi', $code);
        } else {
            $resiQuery->where('id_pesanan', $code);
        }

        $resi = $resiQuery->first();
        if (!$resi) {
            return response()->json([
                'message' => 'Resi tidak ditemukan.',
            ], 422);
        }
        if (($resi->status ?? 'active') === 'canceled') {
            return response()->json([
                'message' => 'Resi sudah dibatalkan.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $existingScan = PackerScanOut::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();
            if ($existingScan) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi sudah discan keluar.',
                ], 422);
            }

            $transit = PackerTransitHistory::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();

            if (!$transit) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi belum masuk transit packer.',
                ], 422);
            }

            if (($transit->status ?? '') === 'selesai') {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi sudah selesai scan out.',
                ], 422);
            }

            $kurirId = $resi->kurir_id;
            if (!$kurirId) {
                $kurirId = Kurir::where('name', 'Tidak ditemukan kurir')->value('id');
                if (!$kurirId) {
                    $kurirId = Kurir::create(['name' => 'Tidak ditemukan kurir'])->id;
                }
            }

            PackerScanOut::create([
                'resi_id' => $resi->id,
                'kurir_id' => $kurirId,
                'scan_type' => $type,
                'scan_code' => $code,
                'scan_date' => now()->toDateString(),
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
            ]);

            $transit->status = 'selesai';
            $transit->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses scan out.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Scan out berhasil.',
            'resi' => [
                'id_pesanan' => $resi->id_pesanan,
                'no_resi' => $resi->no_resi,
            ],
        ]);
    }
}
