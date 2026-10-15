<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Fitur Activity Logs (dulu Audit Log): akses berbasis permission, pencarian,
 * dan filter aksi.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function log(string $action, ?User $actor = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => 1,
            'ip' => '10.0.0.1',
            'created_at' => now(),
        ]);
    }

    public function test_admin_dan_manager_bisa_melihat_activity_logs(): void
    {
        $this->log('machine.create');

        foreach (['admin', 'manager'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('admin.activity-logs'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Admin/ActivityLogs')
                    ->has('logs.data', 1)
                    ->has('actions'));
        }
    }

    public function test_teknisi_tidak_boleh_melihat_activity_logs(): void
    {
        $this->actingAs($this->user('technician'))
            ->get(route('admin.activity-logs'))
            ->assertForbidden();
    }

    public function test_filter_aksi_hanya_mengembalikan_aksi_yang_diminta(): void
    {
        $this->log('machine.create');
        $this->log('sparepart.update');

        $this->actingAs($this->user('admin'))
            ->get(route('admin.activity-logs', ['action' => 'machine.create']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'machine.create'));
    }

    public function test_pencarian_mencocokkan_nama_pelaku(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Aktivitas']);
        $this->log('machine.update', $budi);

        $this->actingAs($this->user('admin'))
            ->get(route('admin.activity-logs', ['q' => 'Budi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1));
    }

    public function test_permission_lama_audit_view_sudah_tidak_dipakai(): void
    {
        // Rename audit.view -> activity.view; pastikan role memakai nama baru.
        $this->assertTrue($this->user('admin')->hasPermissionTo('activity.view'));
        $this->assertTrue($this->user('manager')->hasPermissionTo('activity.view'));
    }
}
