<?php

namespace App\Http\Controllers\Admin;

use App\Exports\TokoReportExport;
use App\Http\Controllers\Controller;
use App\Support\TokoFilter;
use App\Support\TokoReport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TokoReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.toko.index', [
            'dataUrl' => route('admin.reports.toko.data'),
            'exportUrl' => route('admin.reports.toko.export'),
            'tokos' => TokoFilter::tokoOptions(),
            'channels' => TokoFilter::channelOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $filters = TokoReport::filters($request->all());
        $perToko = TokoReport::perToko($filters);

        return response()->json([
            'filters' => $filters,
            'summary' => TokoReport::summary($perToko),
            'data' => $perToko,
        ]);
    }

    public function export(Request $request)
    {
        $filters = TokoReport::filters($request->all());

        return Excel::download(
            new TokoReportExport($filters, $request->user()?->name ?? '-'),
            'laporan-toko-'.$filters['date_from'].'-'.$filters['date_to'].'.xlsx'
        );
    }
}
