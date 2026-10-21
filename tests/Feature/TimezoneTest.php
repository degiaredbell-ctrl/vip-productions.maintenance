<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Semua tanggal & waktu aplikasi memakai WIB (Asia/Jakarta, UTC+7).
 */
class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_aplikasi_berjalan_di_wib(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
        $this->assertSame(7 * 3600, now()->getOffset());
    }

    public function test_jam_server_dibagikan_untuk_jam_wib_di_header(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-10-21 08:15:30', 'Asia/Jakarta'));

        $this->actingAs(User::factory()->create()->assignRole('technician'))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('serverNow', now()->getTimestampMs()));
    }
}
