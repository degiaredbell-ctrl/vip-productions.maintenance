<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Role selain Admin yang diberi permission Kelola Pengguna tidak boleh
 * melihat/memberikan role Admin, dan tidak boleh mengutak-atik akun Admin.
 */
class UserAdminRoleGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        Role::create(['name' => 'hr'])->givePermissionTo('user.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create()->assignRole('admin');
        $this->hr = User::factory()->create()->assignRole('hr');
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_pilihan_role_admin_disembunyikan_untuk_non_admin(): void
    {
        $this->actingAs($this->hr)->get(route('admin.users'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('roles', fn ($roles) => ! collect($roles)->contains('admin') && collect($roles)->contains('technician'))
                ->where('users', fn ($users) => collect($users)->firstWhere('id', $this->admin->id)['locked'] === true
                    && collect($users)->firstWhere('id', $this->hr->id)['locked'] === false));

        $this->actingAs($this->admin)->get(route('admin.users'))
            ->assertInertia(fn ($page) => $page
                ->where('roles', fn ($roles) => collect($roles)->contains('admin'))
                ->where('users', fn ($users) => collect($users)->every(fn ($u) => $u['locked'] === false)));
    }

    public function test_non_admin_tidak_bisa_memberi_role_admin_lewat_request_langsung(): void
    {
        $this->actingAs($this->hr)->post(route('admin.users.store'), [
            'name' => 'Penyusup', 'email' => 'x@redbellgroup.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'admin',
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'x@redbellgroup.com']);

        $tech = User::factory()->create()->assignRole('technician');
        $this->actingAs($this->hr)->put(route('admin.users.update', $tech), ['role' => 'admin'])
            ->assertSessionHasErrors('role');
        $this->assertFalse($tech->fresh()->hasRole('admin'));

        // Role lain tetap bisa diatur.
        $this->actingAs($this->hr)->put(route('admin.users.update', $tech), ['role' => 'viewer'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($tech->fresh()->hasRole('viewer'));
    }

    public function test_non_admin_tidak_bisa_mengubah_reset_atau_menghapus_akun_admin(): void
    {
        $this->actingAs($this->hr)->put(route('admin.users.update', $this->admin), ['role' => 'viewer'])->assertForbidden();
        $this->actingAs($this->hr)->put(route('admin.users.update-password', $this->admin), [
            'password' => 'diambilalih1', 'password_confirmation' => 'diambilalih1',
        ])->assertForbidden();
        $this->actingAs($this->hr)->put(route('admin.users.update-pin', $this->admin), ['pin' => '482915', 'pin_confirmation' => '482915'])->assertForbidden();
        $this->actingAs($this->hr)->delete(route('admin.users.destroy', $this->admin))->assertForbidden();

        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
        $this->assertModelExists($this->admin);
    }

    public function test_admin_tetap_bisa_memberi_role_admin(): void
    {
        $tech = User::factory()->create()->assignRole('technician');

        $this->actingAs($this->admin)->put(route('admin.users.update', $tech), ['role' => 'admin'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($tech->fresh()->hasRole('admin'));
    }
}
