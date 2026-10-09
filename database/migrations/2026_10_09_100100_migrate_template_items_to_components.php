<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Data lama (template + item) dipindahkan ke master Komponen dan diikat ke
     * setiap Mesin/Utility sesuai template yang dipakai sebelumnya.
     */
    public function up(): void
    {
        app(\App\Services\ComponentMigrationService::class)->migrateFromTemplates();
    }

    /**
     * Tidak ada pembalikan: tabel dihapus oleh migrasi pembuat tabel.
     */
    public function down(): void
    {
    }
};