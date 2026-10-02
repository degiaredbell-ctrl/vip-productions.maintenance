<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view', 'pm.fill', 'pm.history.view',
            'report.view', 'report.export', 'machine.manage', 'template.manage',
            'user.manage', 'audit.view',
            // Tiga permission terpisah, satu per tahap rantai persetujuan.
            'pm.sign', 'pm.acknowledge', 'pm.approve',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $roles = [
            // Admin memegang semua permission, jadi bisa menandatangani tahap
            // mana pun bila belum ada orang lain yang menanganinya.
            'admin' => $permissions,
            // Atasan = tahap 3. Tidak boleh mengisi checklist lapangan.
            'manager' => ['dashboard.view', 'pm.approve', 'pm.history.view', 'report.view', 'report.export', 'machine.manage', 'template.manage', 'audit.view'],
            // Technician mengisi checklist (pm.fill) lalu menandatanganinya (pm.sign).
            'technician' => ['dashboard.view', 'pm.fill', 'pm.sign', 'pm.history.view', 'report.view', 'report.export'],
            // User = tahap 2 (User PIC): menyetujui sebagai diketahui.
            'user' => ['dashboard.view', 'pm.acknowledge', 'pm.history.view', 'report.view'],
            'viewer' => ['dashboard.view', 'pm.history.view', 'report.view', 'report.export'],
        ];

        foreach ($roles as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePerms);
        }
    }
}
