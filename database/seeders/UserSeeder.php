<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Administrator', 'email' => 'admin@redbellgroup.com', 'password' => 'password', 'role' => 'admin'],
            ['name' => 'Manager Maintenance', 'email' => 'manager.maintenance@redbellgroup.com', 'password' => 'password', 'role' => 'manager'],
            ['name' => 'Teknisi M1', 'email' => 'teknisi1.maintenance@redbellgroup.com', 'password' => 'password', 'role' => 'technician'],
            ['name' => 'Teknisi M2', 'email' => 'teknisi2.maintenance@redbellgroup.com', 'password' => 'password', 'role' => 'technician'],
            ['name' => 'User 1', 'email' => 'user1@redbellgroup.com', 'password' => 'password', 'role' => 'user'],
            ['name' => 'User 2', 'email' => 'user2@redbellgroup.com', 'password' => 'password', 'role' => 'user'],
            ['name' => 'Viewer Maintenance', 'email' => 'viewer.maintenance@redbellgroup.com', 'password' => 'password', 'role' => 'viewer']
        ];

        foreach ($users as $u) {
            $user = User::create([
                'name' => $u['name'],
                'email' => $u['email'],
                'password' => Hash::make($u['password']),
            ]);
            $user->assignRole($u['role']);
        }
    }
}
