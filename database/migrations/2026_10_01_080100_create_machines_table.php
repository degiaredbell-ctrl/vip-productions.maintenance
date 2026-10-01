<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 30)->default('generic');
            $table->unsignedTinyInteger('week_group')->default(1);
            $table->foreignId('template_id')->nullable()->constrained('checklist_templates')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_no')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_active', 'week_group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
