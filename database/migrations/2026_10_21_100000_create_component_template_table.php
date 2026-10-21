<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Template kini berupa kumpulan Komponen dari master Komponen / Parts
 * (bukan lagi item teks bebas), lengkap dengan urutan dan Standar default.
 *
 * - `component_template`: komponen milik sebuah template.
 * - `checklist_templates.scope`: untuk Mesin atau Utility.
 * - `checklist_templates.description`: keterangan singkat (opsional).
 *
 * Item template lama dipetakan ke komponen yang sama (nama + kategori + spec,
 * tanpa beda huruf besar/kecil & spasi). Komponen yang belum ada dibuat, sama
 * seperti ComponentMigrationService. Tabel `checklist_template_items` tidak
 * dihapus supaya data lama tetap bisa ditelusuri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_templates', function (Blueprint $table) {
            $table->string('scope', 10)->default('machine')->after('machine_type');
            $table->string('description')->nullable()->after('name');
        });

        Schema::create('component_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('checklist_templates')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('components')->cascadeOnDelete();
            $table->unsignedInteger('sort_no')->default(0);
            $table->string('standard')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'component_id']);
            $table->index(['template_id', 'sort_no']);
        });

        DB::table('checklist_templates')
            ->where('machine_type', 'utility')
            ->orWhere('machine_type', 'like', 'utility:%')
            ->update(['scope' => 'utility']);

        $key = fn ($name, $category, $spec) => implode('|', [
            mb_strtolower(trim((string) $name)),
            mb_strtolower(trim((string) $category)),
            mb_strtolower(trim((string) $spec)),
        ]);

        $components = [];
        foreach (DB::table('components')->get(['id', 'name', 'category', 'spec']) as $c) {
            $components[$key($c->name, $c->category, $c->spec)] ??= $c->id;
        }

        $now = now();
        $items = DB::table('checklist_template_items')->orderBy('template_id')->orderBy('sort_no')->orderBy('id')->get();

        foreach ($items->groupBy('template_id') as $templateId => $rows) {
            $order = 1;
            $seen = [];

            foreach ($rows as $item) {
                $k = $key($item->name, $item->category, $item->spec);

                if (! isset($components[$k])) {
                    $components[$k] = DB::table('components')->insertGetId([
                        'name' => $item->name,
                        'category' => $item->category,
                        'spec' => $item->spec,
                        'sort_no' => (int) $item->sort_no,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $componentId = $components[$k];
                if (isset($seen[$componentId])) {
                    continue;
                }
                $seen[$componentId] = true;

                DB::table('component_template')->insert([
                    'template_id' => $templateId,
                    'component_id' => $componentId,
                    'sort_no' => $order++,
                    'standard' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('component_template');

        Schema::table('checklist_templates', function (Blueprint $table) {
            $table->dropColumn(['scope', 'description']);
        });
    }
};
