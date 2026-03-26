<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;

class PickerDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $roles = $user ? $user->roles()->pluck('slug') : collect();
        $hasPicker = $roles->contains('picker');
        $hasPacker = $roles->contains('packer');
        $hasAdminScan = $roles->contains('admin-scan');
        $hasOtherRoles = $roles->diff(['picker', 'packer', 'admin-scan'])->isNotEmpty();
        $isAdminScanOnly = $hasAdminScan && !$hasPicker && !$hasPacker && !$hasOtherRoles;

        return view('picker.dashboard', [
            'routes' => [
                'opname' => route('opname.index'),
                'picker' => route('picker.index'),
                'packer' => route('picker.packer.index'),
                'scanOut' => route('picker.scan-out.index'),
                'scanOutV2' => route('picker.scan-out-v2.index'),
                'pickingList' => route('picker.picking-list.index'),
                'logout' => route('logout'),
            ],
            'showPicking' => ($hasPicker || $hasPacker || $hasOtherRoles) && !$isAdminScanOnly,
            'showPacking' => ($hasPicker || $hasPacker || $hasOtherRoles) && !$isAdminScanOnly,
            'showScanOut' => ($hasAdminScan || $hasOtherRoles) && !$hasPicker && !$hasPacker,
            'showScanOutV2' => ($hasAdminScan || $hasOtherRoles) && !$hasPicker && !$hasPacker,
            'showPickingList' => ($hasPicker || $hasPacker || $hasOtherRoles) && !$isAdminScanOnly,
        ]);
    }
}
