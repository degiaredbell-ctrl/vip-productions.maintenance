<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar pengguna resmi dikunci di sini supaya ada yang bisa menjawab "siapa
 * yang berhak masuk" kalau akun bermasalah: UserSeeder adalah satu-satunya
 * sumber kebenaran, dan seeder itu pun tidak boleh diam-diam kehilangan atau
 * menambah akun.
 */
class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    private const EMAILS = [
        'admin.pm_anwar@redbellgroup.com',
        'admin.pm_djonie@redbellgroup.com',
        'teknisi.pm_dwi@redbellgroup.com',
        'teknisi.pm_padli@redbellgroup.com',
        'teknisi.pm_yopin@redbellgroup.com',
        'teknisi.pm_syarif@redbellgroup.com',
        'teknisi.pm_fadil@redbellgroup.com',
        'romi.fusianto@redbellgroup.com',
        'degia.parlopa@redbellgroup.com',
        'dandy.wijaya@redbellgroup.com',
        'user.pm_sheila@redbellgroup.com',
        'user.pm_anton@redbellgroup.com',
        'user.pm_ririn@redbellgroup.com',
        'user.pm_achmad@redbellgroup.com',
        'admin.pm_administration.maintenance@redbellgroup.com',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_seeder_menghasilkan_daftar_akun_yang_persis(): void
    {
        // User lama yang tidak boleh ikut bertahan.
        User::factory()->create(['email' => 'admin@redbellgroup.com', 'name' => 'Administrator'])->assignRole('admin');
        User::factory()->create(['email' => 'random@local.test'])->assignRole('user');

        $this->seed(UserSeeder::class);

        $this->assertSame(count(self::EMAILS), User::count(), 'Harus persis 15 akun, tidak lebih tidak kurang.');
        foreach (self::EMAILS as $email) {
            $this->assertTrue(
                User::where('email', $email)->exists(),
                'Akun '.$email.' hilang setelah seeder.'
            );
        }

        $this->assertFalse(User::where('email', 'admin@redbellgroup.com')->exists(), 'Akun lama tidak boleh bertahan.');
        $this->assertFalse(User::where('email', 'random@local.test')->exists(), 'Akun asing tidak boleh masuk.');
    }

    public function test_seeder_mengembalikan_role_dan_nama_yang_tepat(): void
    {
        $this->seed(UserSeeder::class);

        $expected = [
            'admin.pm_anwar@redbellgroup.com' => ['admin', 'Anwar'],
            'admin.pm_djonie@redbellgroup.com' => ['admin', 'Djonie'],
            'teknisi.pm_dwi@redbellgroup.com' => ['technician', 'Dwi'],
            'teknisi.pm_fadil@redbellgroup.com' => ['technician', 'Fadil'],
            'romi.fusianto@redbellgroup.com' => ['admin', 'Romi Fusianto'],
            'degia.parlopa@redbellgroup.com' => ['admin', 'Degia Parlopa'],
            'dandy.wijaya@redbellgroup.com' => ['user', 'Dandy Wijaya'],
            'user.pm_sheila@redbellgroup.com' => ['user', 'Sheila'],
            'user.pm_ririn@redbellgroup.com' => ['user', 'Ririn'],
            'admin.pm_administration.maintenance@redbellgroup.com' => ['admin', 'Administration Maintenance'],
        ];

        foreach ($expected as $email => [$role, $name]) {
            $user = User::where('email', $email)->sole();
            $this->assertSame($name, $user->name, 'Nama salah untuk '.$email);
            $this->assertTrue($user->hasRole($role), 'Role '.$role.' hilang dari '.$email);
        }
    }

    public function test_seeder_dapat_dijalankan_ulang_tanpa_berlipat_kali(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(count(self::EMAILS), User::count(), 'Jalankan ulang tidak boleh menambah akun.');
    }

    public function test_password_seeder_bisa_dipakai_login(): void
    {
        $this->seed(UserSeeder::class);

        $cases = [
            ['admin.pm_anwar@redbellgroup.com', 'Anwar@teknis.vip'],
            ['teknisi.pm_dwi@redbellgroup.com', 'Dwi@teknis.vip'],
            ['romi.fusianto@redbellgroup.com', 'password'],
            ['user.pm_anton@redbellgroup.com', 'Anton@teknis.vip'],
        ];

        foreach ($cases as [$email, $password]) {
            $user = User::where('email', $email)->sole();
            $this->assertTrue(
                \Illuminate\Support\Facades\Hash::check($password, $user->password),
                'Password tidak cocok untuk '.$email
            );
        }
    }
}