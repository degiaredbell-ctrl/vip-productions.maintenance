<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_record_id')->constrained('pm_records')->cascadeOnDelete();
            $table->string('stage', 20);

            // Nama & peran disimpan sebagai snapshot supaya jejaknya tetap
            // terbaca meskipun akunnya nanti diganti atau dihapus.
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signed_by_name', 100);
            $table->string('signed_by_role', 30)->nullable();
            $table->string('image_path', 255);
            $table->text('note')->nullable();
            $table->timestamp('signed_at');

            // Satu tanda tangan per tahap; menandatangani ulang (setelah
            // revisi atau penolakan) akan menimpa baris yang sama.
            $table->unique(['pm_record_id', 'stage']);
            $table->index('signed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_signatures');
    }
};
