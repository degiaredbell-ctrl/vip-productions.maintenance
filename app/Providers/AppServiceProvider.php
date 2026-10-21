<?php

namespace App\Providers;

use App\Mail\Transport\MicrosoftGraphTransport;
use App\Notifications\ResetPasswordNotification;
use App\Policies\RolePolicy;
use App\Services\Integration\IntegrationSettings;
use App\Services\Integration\MailConfigurator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
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
        // Scoped: dibaca ulang per request dan per job antrean, jadi
        // perubahan di Settings → Integrate langsung berlaku.
        $this->app->scoped(IntegrationSettings::class);
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

        // Email Microsoft 365 lewat Graph API (lihat Settings → Integrate).
        Mail::extend('microsoft-graph', fn (array $config) => new MicrosoftGraphTransport(
            (string) ($config['tenant_id'] ?? ''),
            (string) ($config['client_id'] ?? ''),
            (string) ($config['client_secret'] ?? ''),
            (string) ($config['sender'] ?? ''),
        ));

        MailConfigurator::apply();
        Event::listen(JobProcessing::class, fn () => MailConfigurator::apply());

        // Email reset password berbahasa Indonesia.
        ResetPassword::toMailUsing(fn ($notifiable, string $token) => (new ResetPasswordNotification($token))->toMail($notifiable));
    }
}
