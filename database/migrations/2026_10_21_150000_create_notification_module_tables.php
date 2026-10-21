<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Modul Settings → Notifications:
 *
 * - `notifications`          : kotak masuk in-app (format bawaan Laravel).
 * - `notification_dispatches`: riwayat pengiriman + kunci anti-duplikat
 *                              (mis. reminder Minggu ke-2 hanya sekali per periode).
 * - `assets.pic_user_id`     : User PIC penanggung jawab perangkat, dipakai
 *                              untuk membatasi notifikasi User/PIC.
 * - permission `notification.manage` (Admin) untuk mengatur matriks notifikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('dedupe_key', 120)->nullable()->unique();
            $table->string('trigger', 20)->default('schedule'); // schedule | manual | event
            $table->string('summary', 500)->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('app_sent')->default(0);
            $table->unsignedInteger('email_sent')->default(0);
            $table->unsignedInteger('items')->default(0);
            $table->string('status', 20)->default('sent'); // sent | skipped | failed
            $table->text('error')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('pic_user_id')->nullable()->after('week_group')
                ->constrained('users')->nullOnDelete();
        });

        $permission = Permission::firstOrCreate(['name' => 'notification.manage']);
        Role::where('name', 'admin')->get()->each(fn (Role $role) => $role->givePermissionTo($permission));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'notification.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pic_user_id');
        });

        Schema::dropIfExists('notification_dispatches');
        Schema::dropIfExists('notifications');
    }
};
