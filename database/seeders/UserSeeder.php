<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun resmi PM Preventive.
 *
 * Seeder ini satu-satunya sumber kebenaran daftar pengguna. Menjalankannya
 * selalu mengembalikan daftar di bawah, apa pun isi tabel saat ini:
 *
 * - Akun dengan email yang sama diperbarui nama/password/rolenya (updateOrCreate),
 *   jadi id-nya dipertahankan dan keterkaitan PM records yang memakainya tetap
 *   menempel.
 * - Akun yang EMAIL-nya tidak tercantum di daftar ini dibuang (`whereNotIn`).
 *   PM records memakai foreign key `nullOnDelete`, jadi record-nya tetap utuh
 *   dan hanya keterkaitan akun (technician_id/pic_user_id/approved_by) yang
 *   dilepas menjadi NULL.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            // Admin
            ['name' => 'Anwar', 'email' => 'admin.pm_anwar@redbellgroup.com', 'password' => 'Anwar@teknis.vip', 'role' => 'admin'],
            ['name' => 'Djonie', 'email' => 'admin.pm_djonie@redbellgroup.com', 'password' => 'Djonie@teknis.vip', 'role' => 'admin'],
            ['name' => 'Romi Fusianto', 'email' => 'romi.fusianto@redbellgroup.com', 'password' => 'password', 'role' => 'admin'],
            ['name' => 'Degia Parlopa', 'email' => 'degia.parlopa@redbellgroup.com', 'password' => 'password', 'role' => 'admin'],
            ['name' => 'Administration Maintenance', 'email' => 'admin.pm_administration.maintenance@redbellgroup.com', 'password' => 'Administration Maintenance@teknis.vip', 'role' => 'admin'],

            // Teknisi
            ['name' => 'Dwi', 'email' => 'teknisi.pm_dwi@redbellgroup.com', 'password' => 'Dwi@teknis.vip', 'role' => 'technician'],
            ['name' => 'Padli', 'email' => 'teknisi.pm_padli@redbellgroup.com', 'password' => 'Padli@teknis.vip', 'role' => 'technician'],
            ['name' => 'Yopin', 'email' => 'teknisi.pm_yopin@redbellgroup.com', 'password' => 'Yopin@teknis.vip', 'role' => 'technician'],
            ['name' => 'Syarif', 'email' => 'teknisi.pm_syarif@redbellgroup.com', 'password' => 'Syarif@teknis.vip', 'role' => 'technician'],
            ['name' => 'Fadil', 'email' => 'teknisi.pm_fadil@redbellgroup.com', 'password' => 'Fadil@teknis.vip', 'role' => 'technician'],

            // User (PIC tahap 2)
            ['name' => 'Dandy Wijaya', 'email' => 'dandy.wijaya@redbellgroup.com', 'password' => 'password', 'role' => 'user'],
            ['name' => 'Sheila', 'email' => 'user.pm_sheila@redbellgroup.com', 'password' => 'Sheila@teknis.vip', 'role' => 'user'],
            ['name' => 'Anton', 'email' => 'user.pm_anton@redbellgroup.com', 'password' => 'Anton@teknis.vip', 'role' => 'user'],
            ['name' => 'Ririn', 'email' => 'user.pm_ririn@redbellgroup.com', 'password' => 'Ririn@teknis.vip', 'role' => 'user'],
            ['name' => 'Achmad', 'email' => 'user.pm_achmad@redbellgroup.com', 'password' => 'Achmad@teknis.vip', 'role' => 'user'],
        ];

        $emails = array_column($users, 'email');

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make($u['password']),
                ]
            );
            // syncRoles, bukan assignRole, supaya akun yang pindah role (mis.
            // dari manager jadi user) tidak menumpuk dua role.
            $user->syncRoles([$u['role']]);
        }

        // Daftar di atas satu-satunya kebenaran. Buang akun lama yang tidak
        // tercantum.
        User::whereNotIn('email', $emails)->delete();
    }
}