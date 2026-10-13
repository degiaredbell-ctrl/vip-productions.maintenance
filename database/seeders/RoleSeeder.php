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
            // Scope aset terpisah per tipe supaya role teknisi bisa dibatasi:
            // machine.view  = akses halaman/daftar Mesin (read-only bila tanpa machine.manage)
            // utility.view  = akses halaman/daftar Utility (read-only bila tanpa utility.manage)
            // utility.manage = CRUD Utility, padanan `machine.manage` untuk mesin.
            'machine.view', 'utility.view', 'utility.manage',
            // Tiga permission terpisah, satu per tahap rantai persetujuan.
            'pm.sign', 'pm.acknowledge', 'pm.approve',
            // Admin bisa kelola (CRUD) semua PM Record
            'pm.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $roles = [
            // Admin memegang semua permission, jadi bisa menandatangani tahap
            // mana pun bila belum ada orang lain yang menanganinya.
            'admin' => [...$permissions, 'pm.manage'],
            // Atasan = tahap 3. Tidak boleh mengisi checklist lapangan.
            'manager' => ['dashboard.view', 'pm.approve', 'pm.history.view', 'report.view', 'report.export', 'machine.manage', 'template.manage', 'audit.view'],
            // Technician mengisi checklist (pm.fill) lalu menandatanganinya (pm.sign).
            'technician' => ['dashboard.view', 'pm.fill', 'pm.sign', 'pm.history.view', 'report.view', 'report.export'],
            // User = tahap 2 (User PIC): menyetujui sebagai diketahui.
            'user' => ['dashboard.view', 'pm.acknowledge', 'pm.history.view', 'report.view'],
            'viewer' => ['dashboard.view', 'pm.history.view', 'report.view', 'report.export'],
            // ------------------------------------------------------------------
            // Role teknisi berbasis scope. Sengaja TANPA `dashboard.view` supaya
            // menu/halaman di luar scope-nya ikut tertutup (guard & navigasi
            // memakai machine.view/utility.view sebagai pengganti).
            // ------------------------------------------------------------------
            // Teknisi khusus Mesin: hanya halaman/daftar mesin, isi PM, riwayat,
            // dan laporan. Tidak bisa membuka /utility maupun form maintenance
            // utility (kartu tampil abu-abu dan URL langsung diblokir).
            'tech-machines' => ['machine.view', 'pm.fill', 'pm.sign', 'pm.history.view', 'report.view', 'report.export'],
            // Teknisi khusus Utility: kebalikan dari tech-machines.
            'tech-utilities' => ['utility.view', 'pm.fill', 'pm.sign', 'pm.history.view', 'report.view', 'report.export'],
            // Teknisi full: mesin & utility boleh CRUD (+utility.manage /
            // machine.manage), form-maintenance lengkap termasuk kartu Komponen
            // (template.manage).
            'tech-all' => ['machine.view', 'machine.manage', 'utility.view', 'utility.manage', 'template.manage', 'pm.fill', 'pm.sign', 'pm.history.view', 'report.view', 'report.export'],
        ];

        foreach ($roles as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePerms);
        }
    }
}
