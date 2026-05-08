<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class PackerScanOutInputController extends Controller
{
    public function index()
    {
        return view('admin.outbound.scan-out.index', [
            'today' => now()->toDateString(),
            'routes' => [
                'dashboard' => route('admin.dashboard'),
                'scan' => route('admin.outbound.scan-out.scan'),
                'history' => route('admin.outbound.packer-scan-outs.index'),
                'historyData' => route('admin.outbound.packer-scan-outs.data'),
                'qcTransit' => route('admin.inventory.picker-transit.index'),
                'qcScan' => route('admin.outbound.qc-scan.index'),
            ],
        ]);
    }
}
