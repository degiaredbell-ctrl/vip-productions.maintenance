<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('machines') && !Schema::hasTable('assets')) {
            Schema::rename('machines', 'assets');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('assets') && !Schema::hasTable('machines')) {
            Schema::rename('assets', 'machines');
        }
    }
};
