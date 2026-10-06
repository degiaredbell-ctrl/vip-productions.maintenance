<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_records', function (Blueprint $table) {
            // Nama teknisi diketik manual oleh pemeriksa di form, bukan diambil
            // dari akun yang login: di lapangan orang yang mengisi checklist
            // tidak selalu orang yang squeez-in-nya.
            $table->string('technician_name', 100)->nullable()->after('technician_id');
        });
    }

    public function down(): void
    {
        Schema::table('pm_records', function (Blueprint $table) {
            $table->dropColumn('technician_name');
        });
    }
};