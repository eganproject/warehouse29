<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class QcScanInputController extends Controller
{
    public function index()
    {
        return view('admin.outbound.qc-scan.index', [
            'today' => now()->toDateString(),
            'routes' => [
                'dashboard' => route('admin.dashboard'),
                'pickerTransit' => route('admin.inventory.picker-transit.index'),
                'pickingList' => route('admin.inventory.picking-list.index'),

                // Reuse existing QC scan (mobile) endpoints to avoid duplicate business logic.
                'qcCurrent' => route('picker.current'),
                'qcStart' => route('picker.start'),
                'qcScanItem' => route('picker.scan-item'),
                'qcItemsStore' => route('picker.items.store'),
                'qcItemsUpdate' => route('picker.items.update', ':id'),
                'qcItemsDestroy' => route('picker.items.destroy', ':id'),
                'qcSubmit' => route('picker.submit'),
                'qcSearchItems' => route('picker.items.search'),

                // Picking list reference (mobile JSON endpoint)
                'pickingListData' => route('picker.picking-list.data'),
            ],
        ]);
    }
}

