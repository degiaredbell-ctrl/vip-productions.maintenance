<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_record_items', function (Blueprint $table) {
            // Detail bagian yang diganti. Diwajibkan lewat form ketika aksi
            // "Ganti" dicentang, supaya riwayat PM menjelaskan komponen apa
            // yang benar-benar diganti, bukan sekadar ada tanda centang.
            $table->string('replace_note', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pm_record_items', function (Blueprint $table) {
            $table->dropColumn('replace_note');
        });
    }
};
