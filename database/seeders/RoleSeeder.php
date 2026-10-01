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
            'dashboard.view', 'pm.fill', 'pm.approve', 'pm.history.view',
            'report.view', 'report.export', 'machine.manage', 'template.manage',
            'user.manage', 'audit.view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $roles = [
            'admin' => $permissions,
            'manager' => ['dashboard.view', 'pm.approve', 'pm.history.view', 'report.view', 'report.export', 'machine.manage', 'template.manage', 'audit.view'],
            'technician' => ['dashboard.view', 'pm.fill', 'pm.history.view', 'report.view', 'report.export'],
            'user' => ['dashboard.view', 'pm.history.view', 'report.view'],
            'viewer' => ['dashboard.view', 'pm.history.view', 'report.view', 'report.export'],
        ];

        foreach ($roles as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePerms);
        }
    }
}
