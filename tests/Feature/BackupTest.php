<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Fitur Backup & Restore: akses khusus admin (`backup.manage`) dan penjagaan
 * konfirmasi sebelum restore.
 *
 * Catatan: suite test memakai SQLite in-memory, sedangkan backup/restore hanya
 * mendukung MySQL. Karena itu jalur sukses dump/restore tidak diuji di sini;
 * yang diuji adalah pembatasan akses dan penanganan koneksi yang tidak didukung.
 */
class BackupTest extends TestCase
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

    public function test_admin_bisa_membuka_halaman_backup(): void
    {
        $this->actingAs($this->user('admin'))
            ->get(route('admin.backups'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Backups')
                ->has('backups')
                ->has('stats'));
    }

    public function test_selain_admin_tidak_boleh_membuka_halaman_backup(): void
    {
        foreach (['manager', 'technician', 'user', 'viewer'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('admin.backups'))
                ->assertForbidden();
        }
    }

    public function test_backup_gagal_dengan_pesan_jelas_bila_koneksi_bukan_mysql(): void
    {
        // SQLite tidak didukung -> controller menangkap error dan menampilkan
        // flash error, bukan melempar 500 ke pengguna.
        $this->actingAs($this->user('admin'))
            ->from(route('admin.backups'))
            ->post(route('admin.backups.store'))
            ->assertRedirect(route('admin.backups'))
            ->assertSessionHas('error');

        $this->assertSame(0, Backup::count());
    }

    public function test_restore_wajib_konfirmasi_nama_berkas_yang_cocok(): void
    {
        $backup = Backup::create([
            'filename' => 'backup-20260101-020000.sql',
            'size' => 1024,
            'type' => 'manual',
            'status' => 'completed',
            'created_by' => $this->user('admin')->id,
        ]);

        $this->actingAs($this->user('admin'))
            ->post(route('admin.backups.restore', $backup), ['confirm' => 'salah.sql'])
            ->assertSessionHasErrors('confirm');
    }

    public function test_admin_bisa_menghapus_catatan_backup(): void
    {
        $backup = Backup::create([
            'filename' => 'backup-20260101-030000.sql',
            'size' => 2048,
            'type' => 'scheduled',
            'status' => 'completed',
        ]);

        $this->actingAs($this->user('admin'))
            ->delete(route('admin.backups.destroy', $backup))
            ->assertRedirect();

        $this->assertSame(0, Backup::count());
    }

    public function test_permission_backup_manage_hanya_dimiliki_admin(): void
    {
        $this->assertTrue($this->user('admin')->hasPermissionTo('backup.manage'));
        $this->assertFalse($this->user('manager')->hasPermissionTo('backup.manage'));
    }
}
