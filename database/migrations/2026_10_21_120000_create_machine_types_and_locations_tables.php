<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Master data yang bisa di-CRUD dari Settings:
 *
 * - `machine_types`: Tipe Mesin. Sebelumnya daftar tetap di enum
 *   App\Enums\MachineType. Kolom `assets.type` tetap menyimpan `code`, jadi
 *   data unit tidak berubah. `utility` & `generic` ditandai sistem: dipakai
 *   aplikasi (pemisah halaman Utility & tipe cadangan) sehingga kodenya tidak
 *   boleh dihapus.
 * - `locations`: Lokasi / Site. `assets.location` tetap menyimpan nama
 *   lokasi; mengganti nama lokasi di master ikut memperbarui unitnya.
 *
 * Plus permission `masterdata.manage` untuk role admin & tech-all.
 */
return new class extends Migration
{
    private const TYPES = [
        ['filling', 'Filling'],
        ['labeling', 'Labeling'],
        ['inject_blow', 'Inject Blow'],
        ['tom', 'TOM'],
        ['mixer', 'Mixer'],
        ['timbangan', 'Timbangan'],
        ['coding', 'Coding'],
        ['vehicle', 'Kendaraan'],
        ['generic', 'Umum'],
        ['utility', 'Utility'],
    ];

    public function up(): void
    {
        Schema::create('machine_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 50);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_no')->default(0);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_no')->default(0);
            $table->timestamps();
        });

        $now = now();

        foreach (self::TYPES as $i => [$code, $name]) {
            DB::table('machine_types')->insert([
                'code' => $code,
                'name' => $name,
                'is_system' => in_array($code, ['utility', 'generic'], true),
                'sort_no' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Tipe yang sudah dipakai unit tapi tidak ada di daftar bawaan tetap
        // didaftarkan supaya tidak ada unit dengan tipe "yatim".
        $known = array_column(self::TYPES, 0);
        $extra = DB::table('assets')->whereNotIn('type', $known)->distinct()->pluck('type');
        foreach ($extra as $i => $code) {
            DB::table('machine_types')->insert([
                'code' => $code,
                'name' => ucwords(str_replace(['_', '-'], ' ', $code)),
                'is_system' => false,
                'sort_no' => count($known) + $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $locations = DB::table('assets')
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->orderBy('location')
            ->pluck('location');

        foreach ($locations as $i => $name) {
            DB::table('locations')->insert([
                'name' => $name,
                'sort_no' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permission = Permission::firstOrCreate(['name' => 'masterdata.manage']);
        Role::whereIn('name', ['admin', 'tech-all'])->get()->each(
            fn (Role $role) => $role->givePermissionTo($permission)
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'masterdata.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('locations');
        Schema::dropIfExists('machine_types');
    }
};
