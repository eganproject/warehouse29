<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
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
                'logout' => route('logout'),
            ],
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

            PackerScanOut::create([
                'resi_id' => $resi->id,
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
