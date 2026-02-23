<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $roles = Role::orderBy('name')->get(['id', 'name']);
        return view('admin.masterdata.users.index', compact('roles'));
    }

    public function data(Request $request)
    {
        $query = User::with('roles:id,name')->orderBy('name');

        if ($roleId = $request->integer('role_id')) {
            $query->whereHas('roles', fn ($q) => $q->where('roles.id', $roleId));
        }

        if ($search = trim((string) $request->input('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
                'roles' => $u->roles->pluck('name')->implode(', '),
            ];
        });

        return response()->json(['data' => $users]);
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();
        return view('admin.masterdata.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:6','max:100'],
            'roles' => ['nullable','array'],
            'roles.*' => ['integer','exists:roles,id'],
            'avatar' => ['nullable','image','mimes:jpg,jpeg,png','max:2048'],
        ]);
        $avatarPath = null;
        $storedAvatar = null;

        DB::beginTransaction();
        try {
            if ($request->file('avatar')) {
                $storedAvatar = $request->file('avatar')->store('avatars', 'public');
                $avatarPath = 'storage/'.$storedAvatar;
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'avatar' => $avatarPath ?: User::defaultAvatar(),
                'email_verified_at' => now(),
            ]);
            if (!empty($validated['roles'])) {
                $user->roles()->sync($validated['roles']);
            }

            DB::commit();
            return redirect()->route('admin.masterdata.users.index')->with('success', 'User berhasil dibuat');
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($storedAvatar) {
                Storage::disk('public')->delete($storedAvatar);
            }
            return back()->withErrors(['user' => 'Gagal membuat user: '.$e->getMessage()])->withInput();
        }
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        $user->load('roles');
        return view('admin.masterdata.users.edit', compact('user','roles'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'password' => ['nullable','string','min:6','max:100'],
            'roles' => ['nullable','array'],
            'roles.*' => ['integer','exists:roles,id'],
            'avatar' => ['nullable','image','mimes:jpg,jpeg,png','max:2048'],
        ]);
        $update = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];
        if (!empty($validated['password'])) {
            $update['password'] = Hash::make($validated['password']);
        }

        $newAvatarPath = null;
        $oldAvatarPath = null;

        DB::beginTransaction();
        try {
            if ($request->file('avatar')) {
                $newAvatarPath = $request->file('avatar')->store('avatars', 'public');
                $update['avatar'] = 'storage/'.$newAvatarPath;
                if ($user->avatar && str_starts_with($user->avatar, 'storage/avatars/')) {
                    $oldAvatarPath = str_replace('storage/', '', $user->avatar);
                }
            }

            $user->update($update);
            $user->roles()->sync($validated['roles'] ?? []);
            DB::commit();

            if ($oldAvatarPath) {
                Storage::disk('public')->delete($oldAvatarPath);
            }

            return redirect()->route('admin.masterdata.users.index')->with('success', 'User berhasil diperbarui');
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($newAvatarPath) {
                Storage::disk('public')->delete($newAvatarPath);
            }
            return back()->withErrors(['user' => 'Gagal memperbarui user: '.$e->getMessage()])->withInput();
        }
    }

    public function destroy(User $user)
    {
        $avatarPath = null;
        if ($user->avatar && str_starts_with($user->avatar, 'storage/avatars/')) {
            $avatarPath = str_replace('storage/', '', $user->avatar);
        }

        DB::beginTransaction();
        try {
            $user->roles()->detach();
            $user->delete();
            DB::commit();

            if ($avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }

            return redirect()->route('admin.masterdata.users.index')->with('success', 'User berhasil dihapus');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['user' => 'Gagal menghapus user: '.$e->getMessage()]);
        }
    }
}
