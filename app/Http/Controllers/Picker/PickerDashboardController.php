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

        return view('picker.dashboard', [
            'routes' => [
                'opname' => route('opname.index'),
                'picker' => route('picker.index'),
                'logout' => route('logout'),
            ],
            'showPicking' => $hasPicker && !$hasPacker,
        ]);
    }
}
