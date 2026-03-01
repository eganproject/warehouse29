<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PickerSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PickerHistoryController extends Controller
{
    public function index()
    {
        $authUser = request()->user();
        $userQuery = User::orderBy('name');
        if ($authUser) {
            $divisiId = $authUser->divisi_id;
            if ($divisiId !== null && (int) $divisiId !== 1) {
                $userQuery->where('divisi_id', $divisiId);
            }
        }
        $users = $userQuery->get(['id', 'name']);

        return view('admin.outbound.picker-sessions.index', [
            'dataUrl' => route('admin.outbound.picker-sessions.data'),
            'users' => $users,
        ]);
    }

    public function data(Request $request)
    {
        $authUser = $request->user();

        $baseQuery = PickerSession::query()
            ->with(['items.item', 'user'])
            ->orderBy('started_at', 'desc');

        if ($authUser) {
            $divisiId = $authUser->divisi_id;
            if ($divisiId !== null && (int) $divisiId !== 1) {
                $baseQuery->whereHas('user', function ($q) use ($divisiId) {
                    $q->where('divisi_id', $divisiId);
                });
            }
        }

        $query = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        $this->applyDateFilter($query, $request);

        $recordsTotal = (clone $baseQuery)->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $items = $row->items ?? collect();
            $labels = $items->map(function ($it) {
                $sku = trim($it->item?->sku ?? '');
                if ($sku === '') {
                    return '';
                }
                $qty = (int) ($it->qty ?? 0);
                return sprintf('%s (%d)', $sku, $qty);
            })->filter()->values();

            $totalQty = (int) $items->sum('qty');
            $started = $row->started_at ? Carbon::parse($row->started_at)->format('Y-m-d H:i') : '';
            $submitted = $row->submitted_at ? Carbon::parse($row->submitted_at)->format('Y-m-d H:i') : '';

            return [
                'id' => $row->id,
                'code' => $row->code,
                'picker' => $row->user?->name ?? '-',
                'status' => $row->status,
                'started_at' => $started,
                'submitted_at' => $submitted,
                'item' => $labels->implode(', ') ?: '-',
                'qty' => $totalQty,
                'note' => $row->note ?? '',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('started_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('started_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }
}
