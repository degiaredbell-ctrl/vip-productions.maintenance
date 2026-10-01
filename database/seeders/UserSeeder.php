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
            ['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin'],
            ['name' => 'Manager', 'email' => 'manager@example.test', 'password' => 'password', 'role' => 'manager'],
            ['name' => 'Teknisi', 'email' => 'tech@example.test', 'password' => 'password', 'role' => 'technician'],
            ['name' => 'User', 'email' => 'user@example.test', 'password' => 'password', 'role' => 'user'],
            ['name' => 'Viewer', 'email' => 'viewer@example.test', 'password' => 'password', 'role' => 'viewer'],
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
