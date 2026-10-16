<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemakaian suku cadang ke unit (Mesin/Utility) per periode PM.
 *
 * Satu pemakaian disimpan sebagai transaksi stok keluar (`type = out`) yang
 * mencatat tujuan pemakaian: unit (`machine_id`, tabel `assets`) dan
 * periode/tahun PM. Kolom dibuat nullable supaya transaksi stok lama
 * (masuk/keluar tanpa unit) tetap valid.
 *
 * Tidak memakai foreign key constraint karena SQLite (dipakai test) tidak bisa
 * menambahkan FK lewat ALTER TABLE; integritas dijaga di aplikasi lewat
 * validasi `exists:assets,id`. Index tetap dipasang untuk query daftar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spare_part_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('machine_id')->nullable()->after('spare_part_id');
            $table->unsignedSmallInteger('year')->nullable()->after('note');
            $table->string('period', 20)->nullable()->after('year');

            $table->index(['machine_id', 'year', 'period']);
        });
    }

    public function down(): void
    {
        Schema::table('spare_part_movements', function (Blueprint $table) {
            $table->dropIndex(['machine_id', 'year', 'period']);
            $table->dropColumn(['machine_id', 'year', 'period']);
        });
    }
};
