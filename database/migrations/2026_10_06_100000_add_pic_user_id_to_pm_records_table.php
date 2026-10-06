<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_records', function (Blueprint $table) {
            // User PIC yang dipilih teknisi untuk menandatangani tahap 2.
            // Nullable karena record yang sudah ada sebelum kolom ini tidak punya
            // penugasan; record seperti itu tetap boleh ditandatangani User PIC
            // mana pun yang punya izin, supaya tidak ada antrean lama yang mandek.
            $table->foreignId('pic_user_id')
                ->nullable()
                ->after('technician_name')
                ->constrained('users')
                ->nullOnDelete();

            // Halaman Persetujuan menyaring antrean dengan kombinasi status +
            // pic_user_id, jadi kolom ini ikut diindeks.
            $table->index(['status', 'pic_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pm_records', function (Blueprint $table) {
            $table->dropForeign(['pic_user_id']);
            $table->dropIndex(['status', 'pic_user_id']);
            $table->dropColumn('pic_user_id');
        });
    }
};