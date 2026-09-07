<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StockAsOfReportExport;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\StockPeriodReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class StockAsOfReportController extends Controller
{
    public function index(Request $request, StockPeriodReport $report)
    {
        $filters = $this->filters($request);

        return view('admin.reports.stock-as-of.index', [
            'filters' => $filters,
            'days' => $report->days($filters),
            'summary' => $report->summary($filters),
            'rows' => $report->ordered($filters)->paginate($filters['per_page'])->appends($filters),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'movements' => StockPeriodReport::MOVEMENTS,
        ]);
    }

    public function data(Request $request, StockPeriodReport $report)
    {
        $filters = $this->filters($request);
        $count = $report->query($filters)->count();
        $length = (int) $request->input('length', $filters['per_page']);
        $length = in_array($length, [10, 25, 50, 100], true) ? $length : 25;

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $count,
            'recordsFiltered' => $count,
            'summary' => $report->summary($filters),
            'data' => $report->ordered($filters)->skip(max(0, (int) $request->input('start', 0)))->take($length)->get(),
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);

        return Excel::download(new StockAsOfReportExport($filters), 'laporan-stok-'.$filters['tab'].'-'.$filters['date_from'].'-'.$filters['date_to'].'.xlsx');
    }

    private function filters(Request $request): array
    {
        $values = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'as_of_date' => ['nullable', 'date_format:Y-m-d'],
            'tab' => ['nullable', 'in:balance,movement'],
            'stock_type' => ['nullable', 'in:regular,damaged'],
            'category_id' => ['nullable', 'integer', 'min:0'],
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:positive,zero,negative,low'],
            'movement' => ['nullable', 'in:fast,slow,non'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);
        $end = $values['date_to'] ?? $values['as_of_date'] ?? now()->toDateString();
        $filters = [
            'date_from' => $values['date_from'] ?? Carbon::parse($end)->startOfMonth()->toDateString(),
            'date_to' => $end,
            'tab' => $values['tab'] ?? 'balance',
            'stock_type' => $values['stock_type'] ?? 'regular',
            'category_id' => $values['category_id'] ?? '',
            'q' => trim($values['q'] ?? ''),
            'status' => $values['status'] ?? '',
            'movement' => $values['movement'] ?? '',
            'per_page' => (int) ($values['per_page'] ?? 25),
        ];
        validator($filters, [
            'date_to' => ['after_or_equal:date_from', 'before_or_equal:'.Carbon::parse($filters['date_from'])->addDays(365)->toDateString()],
        ], [
            'date_to.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
            'date_to.before_or_equal' => 'Rentang laporan maksimal 366 hari.',
        ])->validate();

        return $filters;
    }
}
