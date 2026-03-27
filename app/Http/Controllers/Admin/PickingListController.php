<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\PickingListExport;
use App\Models\Item;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\PickerTransitItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class PickingListController extends Controller
{
    public function index()
    {
        return view('admin.inventory.picking-list.index', [
            'dataUrl' => route('admin.inventory.picking-list.data'),
            'dataUrlExceptions' => route('admin.inventory.picking-list.exceptions'),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = PickingList::query()
            ->with('item')
            ->orderBy('list_date', 'desc')
            ->orderBy('sku');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($baseQuery, $request);

        $recordsTotal = PickingList::count();
        $summaryQuery = clone $baseQuery;
        $summary = [
            'ongoing' => (clone $summaryQuery)->where('remaining_qty', '>', 0)->count(),
            'done' => (clone $summaryQuery)->where('remaining_qty', '<=', 0)->count(),
        ];

        $query = clone $baseQuery;
        $this->applyStatusFilter($query, $request);
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $item = $row->item;
            return [
                'date' => $row->list_date?->format('Y-m-d') ?? '-',
                'sku' => $row->sku ?? '-',
                'name' => $item?->name ?? '-',
                'qty' => (int) $row->qty,
                'remaining_qty' => (int) $row->remaining_qty,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function dataExceptions(Request $request)
    {
        $query = PickingListException::query()
            ->with('item')
            ->orderBy('list_date', 'desc')
            ->orderBy('sku');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($query, $request);

        $recordsTotal = PickingListException::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $item = $row->item;
            return [
                'date' => $row->list_date?->format('Y-m-d') ?? '-',
                'sku' => $row->sku ?? '-',
                'name' => $item?->name ?? '-',
                'qty' => (int) $row->qty,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        $filters = [
            'q' => $request->input('q', ''),
            'date' => $request->input('date'),
            'status' => $request->input('status', ''),
        ];

        $date = $filters['date'] ?: now()->toDateString();
        $suffix = $filters['status'] ?: 'all';
        $filename = "picking-list-{$date}-{$suffix}.xlsx";

        return Excel::download(new PickingListExport($filters), $filename);
    }

    public function storeQty(Request $request)
    {
        $validated = $request->validate([
            'list_date' => ['required', 'date'],
            'sku' => ['required', 'string', 'max:100'],
            'qty' => ['required', 'integer', 'min:1'],
            'mode' => ['required', 'in:add,reduce'],
        ]);

        $listDate = Carbon::parse($validated['list_date'])->toDateString();
        $sku = trim($validated['sku']);
        $qty = (int) $validated['qty'];
        $mode = $validated['mode'];
        $delta = $mode === 'reduce' ? -$qty : $qty;

        try {
            $row = DB::transaction(function () use ($listDate, $sku, $delta) {
                $existing = PickingList::where('list_date', $listDate)
                    ->where('sku', $sku)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $newQty = max(0, (int) $existing->qty + $delta);
                    $balances = $this->getPickingBalances($listDate, $sku, $newQty);
                    $existing->qty = $newQty;
                    $existing->remaining_qty = $balances['remaining'];
                    if ($existing->qty <= 0 && $existing->remaining_qty <= 0) {
                        $existing->delete();
                        return null;
                    }
                    $existing->save();
                    $this->syncPickingException($listDate, $sku, $balances['exception']);
                    return $existing;
                }

                if ($delta <= 0) {
                    throw ValidationException::withMessages([
                        'sku' => 'Data picking list tidak ditemukan untuk tanggal tersebut.',
                    ]);
                }

                $balances = $this->getPickingBalances($listDate, $sku, $delta);
                $created = PickingList::create([
                    'list_date' => $listDate,
                    'sku' => $sku,
                    'qty' => $delta,
                    'remaining_qty' => $balances['remaining'],
                ]);
                $this->syncPickingException($listDate, $sku, $balances['exception']);
                return $created;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'Gagal menyimpan data.',
            ], 500);
        }

        $message = $mode === 'reduce' ? 'Qty berhasil dikurangi.' : 'Qty berhasil ditambahkan.';
        if (!$row && $mode === 'reduce') {
            $message = 'Qty berhasil dikurangi dan baris picking list dihapus.';
        }

        return response()->json([
            'message' => $message,
            'data' => $row ? [
                'id' => $row->id,
                'sku' => $row->sku,
                'list_date' => $row->list_date?->format('Y-m-d'),
                'qty' => (int) $row->qty,
                'remaining_qty' => (int) $row->remaining_qty,
            ] : null,
        ]);
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

    private function applyDateFilter($query, Request $request): void
    {
        $date = $request->input('date') ?: now()->toDateString();

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->where('list_date', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function applyStatusFilter($query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if ($status === 'ongoing') {
            $query->where('remaining_qty', '>', 0);
        } elseif ($status === 'done') {
            $query->where('remaining_qty', '<=', 0);
        }
    }
}
