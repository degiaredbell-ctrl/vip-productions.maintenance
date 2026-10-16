<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\StoreUserSignatureRequest;
use App\Models\User;
use App\Rules\ValidPin;
use App\Services\AuditLogService;
use App\Services\SignatureStorage;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            // Tanda tangan tersimpan user, supaya kartu di halaman Profile bisa
            // menampilkan preview + tombol ganti/hapus. Null kalau belum pernah
            // disimpan. `version` dipakai frontend untuk mem-bypass cache
            // preview setelah gambar diganti.
            'savedSignature' => $user->signature_path !== null
                ? [
                    'url' => route('profile.signature.show'),
                    'version' => (int) ($user->updated_at?->timestamp ?? 0),
                ]
                : null,
            'hasPin' => $user->hasPin(),
            'canDeleteAccount' => $user->hasRole('admin'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Hanya admin yang boleh menghapus akunnya sendiri; akun role lain
        // dikelola admin lewat halaman Kelola Pengguna.
        abort_unless($request->user()->hasRole('admin'), 403);

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Simpan/menimpa tanda tangan tersimpan milik user sendiri.
     */
    public function updateSignature(StoreUserSignatureRequest $request): RedirectResponse
    {
        $user = $request->user();
        $previous = $user->signature_path;

        // File lama dihapus setelah yang baru berhasil ditulis, supaya
        // kegagalan menulis tidak menghilangkan tanda tangan yang lama.
        $path = SignatureStorage::storeUserSignature($user, $request->validated('signature'));

        $user->forceFill(['signature_path' => $path])->save();

        if ($previous !== null && $previous !== $path) {
            SignatureStorage::delete($previous);
        }

        return Redirect::route('profile.edit')->with('success', 'Tanda tangan tersimpan dan siap dipakai untuk persetujuan.');
    }

    /**
     * Hapus tanda tangan tersimpan milik user sendiri.
     */
    public function destroySignature(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->signature_path !== null) {
            SignatureStorage::delete($user->signature_path);
            $user->forceFill(['signature_path' => null])->save();
        }

        return Redirect::route('profile.edit')->with('success', 'Tanda tangan tersimpan dihapus.');
    }

    /**
     * Atur/ganti PIN login milik user sendiri. Wajib konfirmasi password
     * supaya sesi yang tertinggal terbuka tidak bisa dipakai memasang PIN.
     */
    public function updatePin(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'pin' => ['required', 'confirmed', new ValidPin($user->id)],
        ]);

        $hadPin = $user->hasPin();
        $user->setPin($validated['pin']);

        AuditLogService::record('user.pin', User::class, $user->id, [
            'name' => $user->name,
            'aksi' => $hadPin ? 'ubah' : 'atur',
            'oleh' => 'sendiri',
        ]);

        return Redirect::route('profile.edit')->with('success', 'PIN login berhasil disimpan.');
    }

    /**
     * Hapus PIN login milik user sendiri.
     */
    public function destroyPin(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->hasPin()) {
            $user->setPin(null);

            AuditLogService::record('user.pin', User::class, $user->id, [
                'name' => $user->name,
                'aksi' => 'hapus',
                'oleh' => 'sendiri',
            ]);
        }

        return Redirect::route('profile.edit')->with('success', 'PIN login dihapus.');
    }

    /**
     * Sajikan PNG tanda tangan tersimpan milik user yang sedang login.
     *
     * Route ini tanpa parameter dan hanya untuk akun sendiri, jadi preview
     * tidak bisa diambil akun lain. Header Cache-Control dibuat private agar
     * gambar yang sudah dihapus tidak tersaji dari cache bersama.
     */
    public function showSignature(Request $request): BinaryFileResponse
    {
        $user = $request->user();

        abort_if($user->signature_path === null || $user->signature_path === '', 404);

        $path = SignatureStorage::absolutePath($user->signature_path);

        abort_if($path === null, 404);

        // Cache-Control ditetapkan sesudah response dibuat: BinaryFileResponse
        // menulis Cache-Control sendiri saat dikonstruksi, jadi header yang lewat
        // array konstruktor bisa digabung/tertimpa (mis. jadi "public").
        $response = response()->file($path, ['Content-Type' => 'image/png']);
        $response->headers->set('Cache-Control', 'private, max-age=86400');

        return $response;
    }
}