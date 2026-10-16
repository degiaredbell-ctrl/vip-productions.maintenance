<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PIN 6 digit sebagai metode login alternatif selain email + password.
 *
 * Login PIN hanya meminta PIN (tanpa email), jadi user dicari dari PIN-nya.
 * Karena itu PIN disimpan sebagai HMAC-SHA256 deterministik dengan APP_KEY
 * (bukan bcrypt), supaya bisa dicari lewat index dan dijaga unik antar user.
 * Konsekuensinya: mengganti APP_KEY membuat semua PIN tidak berlaku lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash', 64)->nullable()->unique()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['pin_hash']);
            $table->dropColumn('pin_hash');
        });
    }
};
