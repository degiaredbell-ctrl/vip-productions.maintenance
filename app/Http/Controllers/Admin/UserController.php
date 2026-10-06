<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::with('roles')->orderBy('name')->get()->map(function (User $u) use ($request) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->roles->first()?->name ?? '-',
                'is_self' => $u->id === $request->user()->id,
            ];
        });

        return Inertia::render('Admin/Users', [
            'users' => $users,
            // Dibaca dari tabel roles, bukan daftar hardcoded, supaya role yang
            // baru dibuat di halaman /admin/roles langsung bisa dipilih di sini.
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::exists('roles', 'name')],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->assignRole($validated['role']);

        AuditLogService::record('user.create', User::class, $user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $validated['role'],
        ]);

        return back()->with('success', "Pengguna {$user->name} berhasil ditambahkan.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        /*
         * Nama dan email tidak bisa diubah lewat halaman ini: daftar akun
         * dikunci oleh UserSeeder (satu-satunya sumber kebenaran), dan nama
         * dipakai ulang untuk tanda tangan tanpa si pengguna mengetik nama
         * mereka sendiri. Yang boleh diubah di sini hanya role.
         */
        $validated = $request->validate([
            'role' => ['required', Rule::exists('roles', 'name')],
        ]);

        $isSelf = $user->id === $request->user()->id;

        /*
         * Admin tidak boleh menurunkan role-nya sendiri. Satu-satunya akun
         * admin yang kehilangan user.manage akan terkunci dari halaman
         * pengelolaan pengguna, dan tidak ada jalan lain untuk memulihkannya
         * karena halaman ini yang mengatur role.
         */
        if ($isSelf && $validated['role'] !== $user->roles->first()?->name) {
            return back()->with('error', 'Tidak dapat mengubah role akun Anda sendiri.');
        }

        $beforeRole = $user->roles->first()?->name;

        $user->syncRoles([$validated['role']]);

        if ($beforeRole !== $validated['role']) {
            AuditLogService::record('user.update', User::class, $user->id, [
                'name' => $user->name,
                'role' => ['dari' => $beforeRole, 'ke' => $validated['role']],
            ]);
        }

        return back()->with('success', "Pengguna {$user->name} berhasil diperbarui.");
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('updatePassword', $user);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => $validated['password']]);

        // Password tidak pernah ikut ditulis ke audit log, hanya akun sasaran
        // dan emailnya supaya jejaknya tetap bisa ditelusuri.
        AuditLogService::record('user.password', User::class, $user->id, [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return back()->with('success', "Password {$user->name} berhasil diubah.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        /*
         * Dicek sebelum authorize() supaya akun sendiri mendapat pesan yang
         * jelas. Kalau dibalik, UserPolicy::delete sudah menolak lebih dulu dan
         * pengguna hanya melihat halaman 403 tanpa penjelasan.
         */
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $this->authorize('delete', $user);

        $snapshot = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
        ];

        /*
         * Riwayat PM tidak ikut hilang: kolom technician_id/approved_by di
         * pm_records memakai nullOnDelete, jadi record dan tanda tangannya
         * tetap terbaca meski akunnya dihapus.
         */
        $pmCount = $user->pmRecords()->count();

        $user->delete();

        AuditLogService::record('user.delete', User::class, $user->id, [
            ...$snapshot,
            'pm_records' => $pmCount,
        ]);

        return back()->with(
            'success',
            $pmCount > 0
                ? "Pengguna {$snapshot['name']} dihapus. {$pmCount} riwayat PM miliknya tetap tersimpan."
                : "Pengguna {$snapshot['name']} berhasil dihapus."
        );
    }
}