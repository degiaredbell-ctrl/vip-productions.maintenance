<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standar (nilai acuan) untuk sebuah komponen bersifat PER UNIT: komponen yang
 * sama bisa punya standar berbeda di mesin/utility yang berbeda. Karena itu
 * nilai disimpan di pivot `component_machine`, bukan di master `components`.
 *
 * Bila kolom ini kosong, form PM/daftar komponen jatuh ke `components.spec`
 * sebagai nilai bawaan, sehingga data lama tetap tampil apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('component_machine', function (Blueprint $table) {
            $table->string('standard', 255)->nullable()->after('sort_no');
        });
    }

    public function down(): void
    {
        Schema::table('component_machine', function (Blueprint $table) {
            $table->dropColumn('standard');
        });
    }
};
