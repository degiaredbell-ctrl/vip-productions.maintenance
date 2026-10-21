<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\SparePart;
use App\Models\User;
use App\Notifications\LoginOtpNotification;
use App\Notifications\LowStockNotification;
use App\Services\Auth\LoginOtp;
use App\Services\Integration\IntegrationSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Settings → Integrate: email Microsoft 365 + fitur yang memakainya.
 */
class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['email' => 'admin@redbellgroup.com'])->assignRole('admin');
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    /** Aktifkan koneksi Graph + fitur tertentu. */
    private function enableMail(array $extra = []): void
    {
        app(IntegrationSettings::class)->set([
            'mail.enabled' => true,
            'mail.driver' => 'graph',
            'mail.from_address' => 'pm-noreply@redbellgroup.com',
            'graph.tenant_id' => 'tenant-123',
            'graph.client_id' => 'client-456',
            'graph.client_secret' => 'rahasia-789',
            ...$extra,
        ]);
        app()->forgetScopedInstances();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'mail_enabled' => true,
            'mail_driver' => 'graph',
            'from_address' => 'pm-noreply@redbellgroup.com',
            'from_name' => 'PM Preventive',
            'graph_tenant_id' => 'tenant-123',
            'graph_client_id' => 'client-456',
            'graph_client_secret' => 'rahasia-789',
            'smtp_host' => 'smtp.office365.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'feature_password_reset' => true,
            'feature_login_otp' => false,
            'login_otp_remember_days' => 30,
            'notify_low_stock' => true,
            'low_stock_recipients' => '',
            ...$overrides,
        ];
    }

    // ---------------------------------------------------------------- Akses

    public function test_halaman_integrate_hanya_untuk_admin(): void
    {
        $this->actingAs($this->admin)->get(route('admin.integrate'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Integrate')->where('status.ready', false));

        foreach (['tech-all', 'manager', 'technician', 'viewer'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role))
                ->get(route('admin.integrate'))
                ->assertForbidden();
        }
    }

    // --------------------------------------------------------- Simpan pengaturan

    public function test_menyimpan_pengaturan_secret_terenkripsi_dan_tidak_dikirim_ke_browser(): void
    {
        $this->actingAs($this->admin)->put(route('admin.integrate.update'), $this->payload())->assertSessionHasNoErrors();

        $row = AppSetting::where('key', 'graph.client_secret')->sole();
        $this->assertTrue($row->is_encrypted);
        $this->assertNotSame('rahasia-789', $row->value);
        $this->assertTrue(app(IntegrationSettings::class)->mailReady());

        // Secret kosong saat menyimpan ulang = pertahankan yang lama.
        $this->actingAs($this->admin)->put(route('admin.integrate.update'), $this->payload(['graph_client_secret' => '', 'from_name' => 'Baru']))->assertSessionHasNoErrors();
        app()->forgetScopedInstances();
        $this->assertSame('rahasia-789', app(IntegrationSettings::class)->get('graph.client_secret'));

        $this->actingAs($this->admin)->get(route('admin.integrate'))
            ->assertInertia(fn ($page) => $page
                ->where('settings.graph_client_secret_set', true)
                ->missing('settings.graph_client_secret')
                ->where('status.ready', true));
    }

    public function test_mengaktifkan_email_wajib_isian_lengkap(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.integrate.update'), $this->payload(['from_address' => '', 'graph_tenant_id' => '', 'graph_client_secret' => '']))
            ->assertSessionHasErrors(['from_address', 'graph_tenant_id']);

        $this->actingAs($this->admin)
            ->put(route('admin.integrate.update'), $this->payload(['graph_client_secret' => '']))
            ->assertSessionHasErrors('graph_client_secret');
    }

    // ----------------------------------------------------- Graph transport

    public function test_email_uji_terkirim_lewat_microsoft_graph(): void
    {
        $this->enableMail();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'token-abc', 'expires_in' => 3600]),
            'graph.microsoft.com/*' => Http::response(null, 202),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.integrate.test'), ['to' => 'it@redbellgroup.com'])
            ->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'login.microsoftonline.com/tenant-123/oauth2/v2.0/token')
            && $r['client_id'] === 'client-456' && $r['grant_type'] === 'client_credentials');

        Http::assertSent(function (HttpRequest $r) {
            if (! str_contains($r->url(), 'graph.microsoft.com/v1.0/users/pm-noreply%40redbellgroup.com/sendMail')) {
                return false;
            }
            $mime = base64_decode($r->body());

            return $r->hasHeader('Authorization', 'Bearer token-abc')
                && str_contains($mime, 'it@redbellgroup.com')
                && str_contains($mime, 'Uji koneksi email');
        });

        app()->forgetScopedInstances();
        $this->assertSame('1', (string) app(IntegrationSettings::class)->get('mail.last_test_ok'));
    }

    public function test_email_uji_gagal_menampilkan_pesan_dari_microsoft(): void
    {
        $this->enableMail();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client', 'error_description' => "AADSTS7000215: Invalid client secret provided.\r\nTrace"], 401),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.integrate.test'), ['to' => 'it@redbellgroup.com'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'AADSTS7000215'));
    }

    // ------------------------------------------------------- Lupa password

    public function test_lupa_password_hanya_aktif_bila_email_siap(): void
    {
        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('canResetPassword', false));
        $this->get(route('password.request'))->assertNotFound();

        $this->enableMail(['feature.password_reset' => true]);
        Notification::fake();

        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('canResetPassword', true));

        $user = User::factory()->create(['email' => 'teknisi@redbellgroup.com']);
        $this->post(route('password.email'), ['email' => 'teknisi@redbellgroup.com'])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $mail = $n->toMail($user);

            return $mail->subject === 'Atur Ulang Password — PM Preventive';
        });

        // Email tidak terdaftar mendapat pesan yang sama (tidak bocor).
        $this->post(route('password.email'), ['email' => 'tidakada@redbellgroup.com'])->assertSessionHas('status');
    }

    // ---------------------------------------------------------- OTP login

    public function test_login_password_meminta_kode_otp_lalu_masuk(): void
    {
        $this->enableMail(['feature.login_otp' => true]);
        Notification::fake();

        $user = User::factory()->create(['email' => 'teknisi@redbellgroup.com']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login.otp'));
        $this->assertGuest();

        $code = null;
        Notification::assertSentTo($user, LoginOtpNotification::class, function (LoginOtpNotification $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        $this->get(route('login.otp'))->assertOk()->assertInertia(fn ($page) => $page->component('Auth/LoginOtp'));

        $wrong = $code === '000000' ? '111111' : '000000';
        $this->post(route('login.otp.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('login.otp.verify'), ['code' => $code, 'trust_device' => true])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertCookie(LoginOtp::COOKIE);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_pin_juga_meminta_otp_dan_perangkat_tepercaya_dilewati(): void
    {
        $this->enableMail(['feature.login_otp' => true]);
        Notification::fake();

        $user = User::factory()->create(['email' => 'teknisi@redbellgroup.com']);
        $user->setPin('482915');

        $this->post(route('login.pin'), ['pin' => '482915'])->assertRedirect(route('login.otp'));
        $this->assertGuest();

        $code = null;
        Notification::assertSentTo($user, LoginOtpNotification::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });

        $response = $this->post(route('login.otp.verify'), ['code' => $code, 'trust_device' => true]);
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === LoginOtp::COOKIE);
        $this->post(route('logout'));

        // Perangkat yang diingat tidak diminta kode lagi.
        $raw = \Illuminate\Cookie\CookieValuePrefix::remove(decrypt($cookie->getValue(), false));
        $this->withCookie(LoginOtp::COOKIE, $raw)
            ->post(route('login.pin'), ['pin' => '482915'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_otp_dilewati_bila_email_belum_siap(): void
    {
        app(IntegrationSettings::class)->set(['feature.login_otp' => true]);
        app()->forgetScopedInstances();

        $user = User::factory()->create();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    // ------------------------------------------------------------ Stok

    public function test_peringatan_stok_hanya_saat_melewati_batas(): void
    {
        $this->enableMail(['notify.low_stock_recipients' => 'gudang@redbellgroup.com']);
        Notification::fake();

        $part = SparePart::create(['code' => 'BRG-1', 'name' => 'Bearing 6204', 'unit' => 'pcs', 'stock' => 10, 'min_stock' => 3]);

        $part->update(['stock' => 5]); // masih aman
        Notification::assertNothingSent();

        $part->update(['stock' => 3]); // aman → menipis
        Notification::assertSentOnDemand(LowStockNotification::class, fn ($n, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === ['gudang@redbellgroup.com']);
        Notification::assertSentOnDemandTimes(LowStockNotification::class, 1);

        $part->update(['stock' => 2]); // tetap menipis → tidak dikirim lagi
        Notification::assertSentOnDemandTimes(LowStockNotification::class, 1);

        $part->update(['stock' => 0]); // menipis → habis
        Notification::assertSentOnDemandTimes(LowStockNotification::class, 2);
    }

    public function test_perintah_darurat_mematikan_otp(): void
    {
        $this->enableMail(['feature.login_otp' => true]);

        $this->artisan('integration:disable-otp')->assertSuccessful();

        app()->forgetScopedInstances();
        $this->assertFalse(app(IntegrationSettings::class)->bool('feature.login_otp'));
    }
}
