<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pengaturan aplikasi yang diubah dari layar (Settings → Integrate), bukan
 * dari .env: koneksi email Microsoft 365, fitur email, dan laporan otomatis.
 * Nilai rahasia (client secret / password SMTP) disimpan terenkripsi.
 *
 * Plus permission `integration.manage` (hanya Admin) karena halaman ini
 * menyimpan kredensial email perusahaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });

        $permission = Permission::firstOrCreate(['name' => 'integration.manage']);
        Role::where('name', 'admin')->get()->each(fn (Role $role) => $role->givePermissionTo($permission));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'integration.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('app_settings');
    }
};
