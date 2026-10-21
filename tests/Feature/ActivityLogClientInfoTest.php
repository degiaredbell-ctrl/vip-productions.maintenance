<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\ClientInfo;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * IP & perangkat di Activity Logs harus akurat dan tidak bisa dipalsukan.
 */
class ActivityLogClientInfoTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        Route::middleware('web')->post('/_test/audit', function () {
            AuditLogService::record('test.action');

            return response()->noContent();
        });
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_mencatat_ip_asli_dan_user_agent(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.20.57'])
            ->withHeader('User-Agent', self::CHROME_WINDOWS)
            ->post('/_test/audit')
            ->assertNoContent();

        $log = AuditLog::sole();
        $this->assertSame('192.168.20.57', $log->ip);
        $this->assertSame(self::CHROME_WINDOWS, $log->user_agent);
    }

    public function test_header_x_forwarded_for_dari_klien_tidak_dipercaya(): void
    {
        // Tanpa TRUSTED_PROXIES, klien tidak bisa memalsukan IP lewat header.
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.20.57'])
            ->withHeader('X-Forwarded-For', '8.8.8.8')
            ->post('/_test/audit');

        $this->assertSame('192.168.20.57', AuditLog::sole()->ip);
    }

    public function test_keterangan_ip_dan_perangkat(): void
    {
        config(['audit.ip_labels' => '192.168.99.247=Gateway VPN / NAT;10.10.0.0/16=Cabang']);

        $this->assertSame('Gateway VPN / NAT', ClientInfo::describeIp('192.168.99.247'));
        $this->assertSame('Cabang', ClientInfo::describeIp('10.10.4.2'));
        $this->assertSame('Internal server (Docker)', ClientInfo::describeIp('172.20.0.1'));
        $this->assertSame('Server lokal', ClientInfo::describeIp('127.0.0.1'));
        $this->assertSame('Jaringan lokal', ClientInfo::describeIp('192.168.20.57'));
        $this->assertSame('Internet', ClientInfo::describeIp('103.169.192.229'));
        $this->assertNull(ClientInfo::describeIp(null));

        $this->assertSame('Windows · Chrome', ClientInfo::device(self::CHROME_WINDOWS));
        $this->assertSame('Android (ponsel) · Chrome', ClientInfo::device('Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Mobile Safari/537.36'));
        $this->assertSame('iOS · Safari', ClientInfo::device('Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Windows · Edge', ClientInfo::device('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0'));
        $this->assertNull(ClientInfo::device(null));
    }

    public function test_halaman_activity_logs_menampilkan_keterangan(): void
    {
        config(['audit.ip_labels' => '192.168.99.247=Gateway VPN / NAT']);

        AuditLog::create([
            'action' => 'user.update', 'ip' => '192.168.99.247', 'user_agent' => self::CHROME_WINDOWS, 'created_at' => now(),
        ]);
        AuditLog::create([
            'action' => 'backup.create', 'ip' => null, 'user_agent' => null, 'created_at' => now()->subMinute(),
        ]);

        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.activity-logs', ['q' => '']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('logs.data.0.ip', '192.168.99.247')
                ->where('logs.data.0.ip_label', 'Gateway VPN / NAT')
                ->where('logs.data.0.device', 'Windows · Chrome')
                ->where('logs.data.0.is_system', false)
                ->where('logs.data.1.is_system', true));
    }
}
