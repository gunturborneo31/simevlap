<?php

namespace App\Http\Controllers;

use App\Models\Opd;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with(['opd', 'roles'])->latest()->paginate(20);
        $opds = Opd::where('is_active', true)->get(['id', 'nama', 'singkatan']);
        $roles = Role::all(['id', 'name']);
        return Inertia::render('Pengaturan/User/Index', compact('users', 'opds', 'roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'opd_id' => 'nullable|exists:opds,id',
            'role' => 'required|string|exists:roles,name',
            'is_active' => 'boolean',
        ]);
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'opd_id' => $validated['opd_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);
        $user->assignRole($validated['role']);
        return redirect()->back()->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'opd_id' => 'nullable|exists:opds,id',
            'role' => 'required|string|exists:roles,name',
            'is_active' => 'boolean',
        ]);
        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'opd_id' => $validated['opd_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            ...(isset($validated['password']) ? ['password' => Hash::make($validated['password'])] : []),
        ]);
        $user->syncRoles([$validated['role']]);
        return redirect()->back()->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->back()->with('success', 'User berhasil dihapus.');
    }
}
