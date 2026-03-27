<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\ResiImport;
use App\Models\Item;
use App\Models\Kurir;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\PickerTransitItem;
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
            ->select(['id', 'id_pesanan', 'no_resi', 'tanggal_pesanan', 'kurir_id'])
            ->with(['details' => function ($q) {
                $q->select(['id', 'resi_id', 'sku', 'qty']);
            }, 'kurir'])
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
                'kurir' => $row->kurir?->name ?? '-',
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
            $defaultKurirId = $this->resolveDefaultKurirId();

            foreach ($groups as $group) {
                $existing = Resi::where('id_pesanan', $group['id_pesanan'])->first();
                $oldTanggalUpload = $existing?->tanggal_upload?->format('Y-m-d');
                $oldDetails = $existing
                    ? ResiDetail::where('resi_id', $existing->id)->get(['sku', 'qty'])
                    : collect();

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
                $kurirId = $this->resolveKurirId($group['kurir'] ?? null, $defaultKurirId);
                if ($kurirId) {
                    $payload['kurir_id'] = $kurirId;
                }
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

                if ($existing && $oldTanggalUpload) {
                    $this->adjustPickingList($oldTanggalUpload, $oldDetails, -1);
                }
                $this->adjustPickingList($today, $group['items'], 1);
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

    private function adjustPickingList(string $date, $items, int $direction): void
    {
        $grouped = [];
        foreach ($items as $row) {
            $sku = trim((string) ($row['sku'] ?? $row->sku ?? ''));
            $qty = (int) ($row['qty'] ?? $row->qty ?? 0);
            if ($sku === '' || $qty <= 0) {
                continue;
            }
            $grouped[$sku] = ($grouped[$sku] ?? 0) + $qty;
        }

        foreach ($grouped as $sku => $qty) {
            $delta = $direction * $qty;
            $listRow = PickingList::where('list_date', $date)
                ->where('sku', $sku)
                ->lockForUpdate()
                ->first();

            $newQty = $listRow
                ? max(0, (int) $listRow->qty + $delta)
                : ($delta > 0 ? $delta : 0);
            $balances = $this->getPickingBalances($date, $sku, $newQty);

            if ($listRow) {
                $listRow->qty = $newQty;
                $listRow->remaining_qty = $balances['remaining'];
                if ($listRow->qty <= 0 && $listRow->remaining_qty <= 0) {
                    $listRow->delete();
                } else {
                    $listRow->save();
                }
            } elseif ($delta > 0) {
                PickingList::create([
                    'list_date' => $date,
                    'sku' => $sku,
                    'qty' => $delta,
                    'remaining_qty' => $balances['remaining'],
                ]);
            }
            $this->syncPickingException($date, $sku, $balances['exception']);
        }
    }

    private function getPickingBalances(string $date, string $sku, int $listQty): array
    {
        if ($listQty <= 0) {
            return [
                'remaining' => 0,
                'exception' => $this->getPickedQty($date, $sku),
            ];
        }

        $pickedQty = $this->getPickedQty($date, $sku);
        $remaining = $listQty - $pickedQty;
        if ($remaining < 0) {
            $remaining = 0;
        }
        $exception = $pickedQty - $listQty;
        if ($exception < 0) {
            $exception = 0;
        }

        return [
            'remaining' => $remaining,
            'exception' => $exception,
        ];
    }

    private function getPickedQty(string $date, string $sku): int
    {
        $itemId = Item::where('sku', $sku)->value('id');
        if (!$itemId) {
            return 0;
        }

        return (int) PickerTransitItem::where('item_id', $itemId)
            ->where('picked_date', $date)
            ->value('qty');
    }

    private function syncPickingException(string $date, string $sku, int $exceptionQty): void
    {
        $exception = PickingListException::where('list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if ($exceptionQty > 0) {
            if ($exception) {
                $exception->qty = $exceptionQty;
                $exception->save();
            } else {
                PickingListException::create([
                    'list_date' => $date,
                    'sku' => $sku,
                    'qty' => $exceptionQty,
                ]);
            }
            return;
        }

        if ($exception) {
            $exception->delete();
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
                ->orWhereHas('kurir', function ($kurirQ) use ($search) {
                    $kurirQ->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('details', function ($detailQ) use ($search) {
                    $detailQ->where('sku', 'like', "%{$search}%");
                });
        });
    }

    private function resolveDefaultKurirId(): ?int
    {
        $defaultName = 'Tidak ditemukan kurir';
        $existingId = Kurir::where('name', $defaultName)->value('id');
        if ($existingId) {
            return (int) $existingId;
        }

        $kurir = Kurir::create(['name' => $defaultName]);
        return $kurir->id;
    }

    private function resolveKurirId($rawName, ?int $defaultId): ?int
    {
        $name = trim((string) $rawName);
        if ($name === '') {
            return $defaultId;
        }

        $kurir = Kurir::firstOrCreate(['name' => $name]);
        return $kurir->id ?? $defaultId;
    }
}
