<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-Agent ikut dicatat di Activity Logs. Banyak perangkat bisa tampil
 * dengan IP yang sama (di balik NAT/VPN), jadi perangkat & browser membantu
 * membedakan siapa melakukan apa dari mana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('user_agent', 255)->nullable()->after('ip');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('user_agent');
        });
    }
};
