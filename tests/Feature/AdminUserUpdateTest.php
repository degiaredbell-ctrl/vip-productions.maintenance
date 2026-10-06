<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin.pm_anwar@redbellgroup.com')->sole();
    }

    public function test_nama_dan_email_tidak_bisa_diubah_lewat_form_edit(): void
    {
        $target = User::where('email', 'teknisi.pm_dwi@redbellgroup.com')->sole();

        $response = $this->actingAs($this->admin())->put(
            route('admin.users.update', $target),
            [
                'name' => 'Nama Baru',
                'email' => 'bukan.dwi@redbellgroup.com',
                'role' => 'user',
            ]
        );

        $response->assertRedirect();

        $reloaded = $target->fresh();
        $this->assertSame('Dwi', $reloaded->name, 'Nama tidak boleh berubah.');
        $this->assertSame('teknisi.pm_dwi@redbellgroup.com', $reloaded->email, 'Email tidak boleh berubah.');
        // Perubahan role tetap jalan meski field lain diabaikan.
        $this->assertTrue($reloaded->hasRole('user'));
    }

    public function test_update_hanya_menerima_role(): void
    {
        $target = User::where('email', 'user.pm_sheila@redbellgroup.com')->sole();

        $this->actingAs($this->admin())->put(
            route('admin.users.update', $target),
            ['role' => 'technician']
        )->assertRedirect();

        $this->assertTrue($target->fresh()->hasRole('technician'));
    }

    public function test_admin_tidak_bisa_menurunkan_role_akun_sendiri(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(
            route('admin.users.update', $admin),
            ['role' => 'user']
        )->assertSessionHas('error', 'Tidak dapat mengubah role akun Anda sendiri.');

        $this->assertTrue($admin->fresh()->hasRole('admin'), 'Role sendiri tidak boleh berubah.');
    }
}