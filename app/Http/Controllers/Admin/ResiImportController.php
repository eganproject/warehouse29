<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\ResiImport;
use App\Models\Resi;
use App\Models\ResiDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ResiImportController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();
        $filterDate = trim((string) $request->input('date', ''));
        if ($filterDate === '') {
            $filterDate = $today;
        }
        $search = trim((string) $request->input('q', ''));

        $baseQuery = Resi::query()->whereDate('tanggal_upload', $filterDate);
        $this->applySearch($baseQuery, $search);

        $summaryOrders = (clone $baseQuery)->count();
        $summarySkus = ResiDetail::whereIn('resi_id', (clone $baseQuery)->select('id'))->count();

        return view('admin.inventory.resi-import.index', [
            'importUrl' => route('admin.inventory.resi-import.import'),
            'dataUrl' => route('admin.inventory.resi-import.data'),
            'filterDate' => $filterDate,
            'filterSearch' => $search,
            'today' => $today,
            'summaryOrders' => $summaryOrders,
            'summarySkus' => $summarySkus,
        ]);
    }

    public function data(Request $request)
    {
        $today = now()->toDateString();
        $filterDate = trim((string) $request->input('date', ''));
        if ($filterDate === '') {
            $filterDate = $today;
        }
        $search = trim((string) $request->input('q', ''));

        $filterQuery = Resi::query()->whereDate('tanggal_upload', $filterDate);
        $this->applySearch($filterQuery, $search);

        $recordsTotal = Resi::whereDate('tanggal_upload', $filterDate)->count();
        $summaryOrders = (clone $filterQuery)->count();
        $summarySkus = ResiDetail::whereIn('resi_id', (clone $filterQuery)->select('id'))->count();

        $query = Resi::query()
            ->select(['id', 'id_pesanan', 'no_resi', 'tanggal_pesanan'])
            ->with(['details' => function ($q) {
                $q->select(['id', 'resi_id', 'sku', 'qty']);
            }])
            ->whereDate('tanggal_upload', $filterDate)
            ->orderByDesc('id');

        $this->applySearch($query, $search);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $skuItems = $row->details
                ? $row->details->groupBy('sku')->map(function ($items, $sku) {
                    $total = $items->sum('qty');
                    return $sku.' ('.$total.')';
                })->values()->implode(', ')
                : '-';
            $skuList = $skuItems !== '' ? $skuItems : '-';
            $tanggalOrder = $row->tanggal_pesanan?->format('Y-m-d') ?? $row->tanggal_pesanan ?? '-';
            return [
                'id' => $row->id,
                'no_resi' => $row->no_resi ?? '-',
                'id_pesanan' => $row->id_pesanan ?? '-',
                'sku' => $skuList,
                'tanggal_pesanan' => $tanggalOrder,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $summaryOrders,
            'data' => $data,
            'summary' => [
                'orders' => $summaryOrders,
                'skus' => $summarySkus,
            ],
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new ResiImport();
        DB::beginTransaction();
        try {
            Excel::import($import, $request->file('file'));
            $groups = $import->groups ?? [];
            if (empty($groups)) {
                throw ValidationException::withMessages([
                    'file' => 'Tidak ada data valid untuk diimport',
                ]);
            }

            $createdResi = 0;
            $createdDetails = 0;
            $today = now()->toDateString();

            foreach ($groups as $group) {
                $tanggalPesanan = $this->parseTanggalPesanan($group['tanggal_pesanan'] ?? null);
                if ($tanggalPesanan === null) {
                    throw ValidationException::withMessages([
                        'file' => 'Format tanggal_pembuatan tidak valid untuk ID Pesanan: '.$group['id_pesanan'],
                    ]);
                }

                $payload = [
                    'tanggal_pesanan' => $tanggalPesanan,
                    'tanggal_upload' => $today,
                    'uploader_id' => auth()->id(),
                ];
                $noResi = isset($group['no_resi']) ? trim((string) $group['no_resi']) : '';
                if ($noResi !== '') {
                    $payload['no_resi'] = $noResi;
                }

                $resi = Resi::updateOrCreate(
                    ['id_pesanan' => $group['id_pesanan']],
                    $payload
                );
                $createdResi++;

                ResiDetail::where('resi_id', $resi->id)->delete();
                foreach ($group['items'] as $row) {
                    ResiDetail::create([
                        'resi_id' => $resi->id,
                        'sku' => $row['sku'],
                        'qty' => (int) $row['qty'],
                    ]);
                    $createdDetails++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import resi berhasil',
                'resis' => $createdResi,
                'details' => $createdDetails,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import resi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function parseTanggalPesanan($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject($raw);
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function applySearch($query, string $search): void
    {
        if ($search === '') {
            return;
        }
        $query->where(function ($sub) use ($search) {
            $sub->where('no_resi', 'like', "%{$search}%")
                ->orWhere('id_pesanan', 'like', "%{$search}%")
                ->orWhereHas('details', function ($detailQ) use ($search) {
                    $detailQ->where('sku', 'like', "%{$search}%");
                });
        });
    }
}
