<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            // Kolom ini berasal dari references/data.csv dan belum ada di
            // skema awal, padahal jadi sumber informasi utama daftar mesin.
            $table->string('location', 100)->nullable()->after('name');
            $table->string('category', 50)->nullable()->after('location');
            $table->string('sub_category', 20)->nullable()->after('category');

            $table->index(['is_active', 'sub_category'], 'machines_active_sub_category_index');
        });
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dropIndex('machines_active_sub_category_index');
            $table->dropColumn(['location', 'category', 'sub_category']);
        });
    }
};
