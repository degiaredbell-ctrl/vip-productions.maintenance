<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255);
            $table->unsignedBigInteger('size')->default(0);
            // manual = dibuat dari halaman admin, scheduled = penjadwalan harian.
            $table->string('type', 20)->default('manual');
            // completed = file siap dipakai, failed = proses dump gagal.
            $table->string('status', 20)->default('completed');
            $table->text('error')->nullable();
            // Pelaku pembuat backup; nullOnDelete supaya riwayat backup tidak
            // hilang ketika akun pembuatnya dihapus.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
