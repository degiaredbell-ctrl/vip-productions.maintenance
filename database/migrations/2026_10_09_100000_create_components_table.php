<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 50)->default('Umum');
            $table->string('spec', 255)->nullable();
            $table->unsignedInteger('sort_no')->default(0);
            $table->timestamps();

            $table->index(['category', 'name']);
        });

        Schema::create('component_machine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('components')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->unsignedInteger('sort_no')->default(0);
            $table->timestamps();

            $table->unique(['component_id', 'machine_id']);
            $table->index(['machine_id', 'sort_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_machine');
        Schema::dropIfExists('components');
    }
};