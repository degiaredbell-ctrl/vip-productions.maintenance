<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menambahkan permission `backup.manage` (halaman Backup & Restore) dan
 * memberikannya ke role admin. Ditaruh di migration supaya deployment cukup
 * menjalankan `php artisan migrate` tanpa harus re-seed seluruh role.
 *
 * RoleSeeder tetap diperbarui agar instalasi baru juga mendapatkannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'backup.manage']);

        // Backup menyentuh seluruh isi database, jadi hanya admin yang diberi.
        Role::where('name', 'admin')->get()->each(
            fn (Role $role) => $role->givePermissionTo($permission)
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'backup.manage')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
