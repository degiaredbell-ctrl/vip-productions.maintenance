<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('machine_type', 30);
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('checklist_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('checklist_templates')->cascadeOnDelete();
            $table->string('category', 50);
            $table->string('name');
            $table->string('spec', 255)->nullable();
            $table->unsignedInteger('sort_no')->default(0);
            $table->timestamps();

            $table->index(['template_id', 'sort_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_template_items');
        Schema::dropIfExists('checklist_templates');
    }
};
