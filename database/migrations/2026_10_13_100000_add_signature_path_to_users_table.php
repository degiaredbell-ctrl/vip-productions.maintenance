<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanda tangan tersimpan per akun, untuk dipakai ulang di persetujuan PM.
 *
 * User menggambar tanda tangannya sekali di halaman Profile; gambar itu
 * tersimpan sebagai PNG dan dipakai langsung (tanpa menggambar ulang) di
 * panel persetujuan. Setiap persetujuan menyalin PNG ini ke dokumen PM
 * sendiri, jadi mengubah tanda tangan di profil tidak mengubah dokumen yang
 * sudah ditandatangani sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('signature_path', 255)->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });
    }
};