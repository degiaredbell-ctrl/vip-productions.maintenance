<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission `component.manage` untuk halaman Komponen / Parts, terpisah dari
 * `template.manage` (halaman Template). Hanya role admin dan tech-all yang
 * diberi; role lain yang memegang `template.manage` tetap bisa mengelola
 * Template tetapi tidak lagi membuka master Komponen.
 *
 * Ditaruh di migration supaya deployment cukup `php artisan migrate`.
 * RoleSeeder ikut diperbarui untuk instalasi baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'component.manage']);

        Role::whereIn('name', ['admin', 'tech-all'])->get()->each(
            fn (Role $role) => $role->givePermissionTo($permission)
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'component.manage')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
