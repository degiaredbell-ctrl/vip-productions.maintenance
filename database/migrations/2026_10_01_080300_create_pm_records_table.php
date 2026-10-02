<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('period', 10);
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            // Nilai status: draft, submitted, pic_approved, approved, rejected
            $table->string('status', 20)->default('draft');
            $table->text('general_note')->nullable();
            $table->unsignedInteger('revision_count')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['machine_id', 'year', 'period']);
            $table->index(['year', 'period', 'status']);
            $table->index(['machine_id', 'year']);
        });

        Schema::create('pm_record_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_record_id')->constrained('pm_records')->cascadeOnDelete();
            $table->string('item_name');
            $table->string('category', 50);
            $table->string('spec', 255)->nullable();
            $table->string('actual', 255)->nullable();
            $table->boolean('act_clean')->default(false);
            $table->boolean('act_repair')->default(false);
            $table->boolean('act_lubricate')->default(false);
            $table->boolean('act_replace')->default(false);
            $table->string('final_condition', 50)->nullable();
            $table->unsignedInteger('parts_replaced')->default(0);
            $table->timestamps();

            $table->index('pm_record_id');
        });

        Schema::create('pm_record_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_record_id')->constrained('pm_records')->cascadeOnDelete();
            $table->foreignId('revised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_record_revisions');
        Schema::dropIfExists('pm_record_items');
        Schema::dropIfExists('pm_records');
    }
};
