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
        $hasOtherRoles = $roles->diff(['picker', 'packer'])->isNotEmpty();

        return view('picker.dashboard', [
            'routes' => [
                'opname' => route('opname.index'),
                'picker' => route('picker.index'),
                'packer' => route('picker.packer.index'),
                'scanOut' => route('picker.scan-out.index'),
                'logout' => route('logout'),
            ],
            'showPicking' => $hasPicker || $hasPacker || $hasOtherRoles,
            'showPacking' => $hasPicker || $hasPacker || $hasOtherRoles,
            'showScanOut' => $hasPicker || $hasPacker || $hasOtherRoles,
        ]);
    }
}
