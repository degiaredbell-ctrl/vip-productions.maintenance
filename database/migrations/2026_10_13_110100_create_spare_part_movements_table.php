<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat transaksi stok suku cadang (barang masuk/keluar).
 *
 * Stok terkini disimpan di `spare_parts.stock`; setiap transaksi di sini
 * mengubahnya. Dibuat terpisah supaya jejak siapa-menambah/mengurangi apa
 * tetap utuh dan bisa diaudit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spare_part_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spare_part_id')->constrained('spare_parts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // 'in' = barang masuk, 'out' = barang keluar.
            $table->string('type', 10);
            $table->unsignedInteger('qty');
            $table->date('moved_at');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['spare_part_id', 'moved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_part_movements');
    }
};
