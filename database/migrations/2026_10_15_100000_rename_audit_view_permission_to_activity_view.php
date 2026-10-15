<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fitur "Audit Log" berganti nama menjadi "Activity Logs". Tabel penyimpanan
 * (`audit_logs`) dan model `AuditLog` sengaja tetap dipertahankan supaya data
 * lama tidak perlu dipindahkan; yang berubah hanya permission-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('audit.view', 'activity.view');
    }

    public function down(): void
    {
        $this->rename('activity.view', 'audit.view');
    }

    private function rename(string $from, string $to): void
    {
        $hasFrom = DB::table('permissions')->where('name', $from)->exists();
        $hasTo = DB::table('permissions')->where('name', $to)->exists();

        // Aman diulang: hanya menimpa nama lama bila nama baru belum ada,
        // sehingga relasi permission ke role (lewat id) tetap utuh.
        if ($hasFrom && ! $hasTo) {
            DB::table('permissions')->where('name', $from)->update(['name' => $to]);
        }

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Tabel permission mungkin belum ada pada migrasi paling awal.
        }
    }
};
