<?php

namespace App\Http\Middleware;

use App\Services\SignatureChain;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'roles' => $request->user()->getRoleNames(),
                ] : null,
                'can' => $request->user() ? $request->user()->getAllPermissions()->mapWithKeys(fn ($p) => [$p->name => true]) : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // Badge jumlah antrean persetujuan, ditaruh di share supaya
            // sidebar di semua halaman bisa menampilkannya tanpa tiap controller
            // harus mengirim ulang.
            'pendingApprovals' => fn () => SignatureChain::pendingCountFor($request->user()),
            'ziggy' => fn () => [
                'location' => url()->current(),
            ],
        ]);
    }
}
