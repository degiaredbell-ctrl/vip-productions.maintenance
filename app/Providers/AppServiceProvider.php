<?php

namespace App\Providers;

use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Role milik Spatie, jadi RolePolicy tidak bisa ditemukan otomatis
        // (Laravel menebak App\Models\Role). Tanpa daftar eksplisit ini,
        // authorizeResource() di RoleController memakai can:viewAny,Spatie\...
        // dan hasilnya 403 walaupun role-nya admin.
        Gate::policy(Role::class, RolePolicy::class);
    }
}
