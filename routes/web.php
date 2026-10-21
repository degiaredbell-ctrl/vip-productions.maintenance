<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ComponentController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\MachineTypeController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PmRecordController;
use App\Http\Controllers\PmSignatureController;
use App\Http\Controllers\PmSyncController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\SparePartDashboardController;
use App\Http\Controllers\SparePartUsageController;
use App\Http\Controllers\UtilityController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/form-maintenance', [DashboardController::class, 'formMaintenance'])->name('form-maintenance');
    Route::get('/form-maintenance/mesin', [DashboardController::class, 'formMaintenanceMesin'])
        ->name('form-maintenance.mesin')
        ->middleware('can_any:machine.manage,machine.view,dashboard.view');
    Route::get('/form-maintenance/utility', [DashboardController::class, 'formMaintenanceUtility'])
        ->name('form-maintenance.utility')
        ->middleware('can_any:utility.view,dashboard.view');

    // Form Maintenance - Suku Cadang: catat pemakaian suku cadang ke unit
    // (Mesin/Utility) per periode PM. Semua user login boleh melihat; mencatat
    // pemakaian hanya pemegang `sparepart.manage`.
    Route::get('/form-maintenance/suku-cadang', [SparePartUsageController::class, 'index'])
        ->name('form-maintenance.suku-cadang')
        ->middleware('can:sparepart.view');
    Route::post('/form-maintenance/suku-cadang', [SparePartUsageController::class, 'store'])
        ->name('form-maintenance.suku-cadang.store')
        ->middleware('can:sparepart.manage');
    Route::get('/dashboard/export', [DashboardController::class, 'export'])
        ->name('dashboard.export')
        ->middleware('can:report.export');

    // Settings: pusat kartu menu pengelolaan aset (Mesin, Utility, Komponen /
    // Parts, Suku Cadang), menggantikan item menu terpisah yang dulu tampil
    // sendiri-sendiri di sidebar. Halaman ini hanya menampilkan kartu; otorisasi
    // sebenarnya tetap di rute tujuan masing-masing kartu.
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

    // Settings → Notifications. Kotak masuk in-app terbuka untuk semua user
    // (hanya notifikasi miliknya sendiri); matriks, PIC perangkat, dan kirim
    // manual hanya untuk pemegang `notification.manage`.
    Route::get('/settings/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/read', [NotificationController::class, 'clearRead'])->name('notifications.clear-read');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->whereUuid('id')->name('notifications.open');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->whereUuid('id')->name('notifications.read');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->whereUuid('id')->name('notifications.destroy');

    Route::middleware('can:notification.manage')->group(function () {
        Route::put('/settings/notifications', [NotificationController::class, 'updateMatrix'])->name('notifications.update');
        Route::put('/settings/notifications/pic', [NotificationController::class, 'assignPic'])->name('notifications.pic');
        Route::post('/settings/notifications/preview', [NotificationController::class, 'preview'])
            ->middleware('throttle:20,1')
            ->name('notifications.preview');
        Route::post('/settings/notifications/send', [NotificationController::class, 'send'])
            ->middleware('throttle:6,1')
            ->name('notifications.send');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Tanda tangan tersimpan per akun: disimpan PNG dari canvas di halaman
    // Profile, lalu dipakai ulang untuk persetujuan PM. Preview hanya milik
    // akun sendiri (route tanpa parameter), disajikan lewat URL ber-otorisasi
    // seperti tanda tangan dokumen supaya nama file tidak pernah diekspos.
    Route::get('/profile/signature', [ProfileController::class, 'showSignature'])
        ->name('profile.signature.show');
    Route::post('/profile/signature', [ProfileController::class, 'updateSignature'])
        ->name('profile.signature.store');
    Route::delete('/profile/signature', [ProfileController::class, 'destroySignature'])
        ->name('profile.signature.destroy');

    // PIN login 6 digit. Dibatasi throttle karena pesan "PIN tidak dapat
    // digunakan" (PIN unik) tidak boleh jadi alat menebak PIN milik orang lain.
    Route::put('/profile/pin', [ProfileController::class, 'updatePin'])
        ->middleware('throttle:6,1')
        ->name('profile.pin.update');
    Route::delete('/profile/pin', [ProfileController::class, 'destroyPin'])
        ->name('profile.pin.destroy');

    // Persetujuan (rantai tanda tangan 3 tahap)
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');

    // PM Records — form PM dibuka oleh pengisi checklist (`pm.fill`) maupun
    // approver (`pm.acknowledge` tahap PIC / `pm.approve` tahap Atasan). Bagi
    // approver, form dibuka READ-ONLY supaya bisa meninjau isi checklist lalu
    // menyetujui/menolak lewat panel tanda tangan di halaman yang sama —
    // persetujuan memang terjadi di form PM, bukan di halaman Persetujuan.
    // Menyimpan checklist tetap butuh `pm.fill` (lihat StorePmRecordRequest).
    Route::get('/machines/{machine}/pm', [PmRecordController::class, 'create'])
        ->name('machines.pm.create')
        ->middleware('can_any:pm.fill,pm.acknowledge,pm.approve');
    Route::get('/machines/{machine}/pm-history', [PmRecordController::class, 'pmHistory'])
        ->name('machines.pm.history')
        ->middleware('can:pm.history.view');
    Route::post('/machines/{machine}/pm', [PmRecordController::class, 'store'])
        ->name('machines.pm.store')
        ->middleware('can:pm.fill');

    // Submit checklist versi JSON: dipakai form PM dan antrean offline di
    // perangkat teknisi (lihat PmSyncController).
    Route::get('/pm/sync/ping', [PmSyncController::class, 'ping'])
        ->name('pm.sync.ping');
    Route::post('/pm/sync', [PmSyncController::class, 'store'])
        ->name('pm.sync')
        ->middleware('can:pm.fill');

    // Satu endpoint untuk ketiga tahap; tahap aktif dibaca dari status record
    // supaya rantai tidak bisa dilompati atau diurutkan ulang.
    Route::post('/pm/{record}/sign', [PmRecordController::class, 'sign'])
        ->name('pm.sign');
    Route::post('/pm/{record}/reject', [PmRecordController::class, 'reject'])
        ->name('pm.reject');
    Route::get('/pm/{record}/signature/{stage}', [PmSignatureController::class, 'show'])
        ->name('pm.signatures.show');

    // Machines. Halaman indeks dibuka oleh pemegang machine.manage (kelola
    // penuh) maupun machine.view (role teknisi mesin yang read-only + isi PM).
    Route::get('/machines', [MachineController::class, 'index'])
        ->name('machines.index')
        ->middleware('can_any:machine.manage,machine.view');
    Route::post('/machines', [MachineController::class, 'store'])
        ->name('machines.store')
        ->middleware('can:machine.manage');
    Route::put('/machines/{machine}', [MachineController::class, 'update'])
        ->name('machines.update')
        ->middleware('can:machine.manage');
    Route::delete('/machines/{machine}', [MachineController::class, 'destroy'])
        ->name('machines.destroy')
        ->middleware('can:machine.manage');
    Route::patch('/machines/{machine}/restore', [MachineController::class, 'restore'])
        ->name('machines.restore')
        ->whereNumber('machine')
        ->middleware('can:machine.manage');
    // Edit komponen + standar per unit (pivot) dari halaman Mesin.
    Route::put('/machines/{machine}/components', [MachineController::class, 'updateComponents'])
        ->name('machines.components.update')
        ->middleware('can:machine.manage');
    Route::get('/machines/{machine}/history', [MachineController::class, 'history'])
        ->name('machines.history')
        ->middleware('can:pm.history.view');

    // Utility: daftar unit utility + komponen maintenance. Halaman dapat
    // dikelola (CRUD) oleh pemegang `utility.manage` (Admin & tech-all) lewat
    // rute khusus di bawah; pemegang `utility.view` (tech-utilities) dan role
    // general (`dashboard.view`) tetap bisa membuka form PM unit dari sini.
    Route::get('/utility', [UtilityController::class, 'index'])
        ->name('utility.index')
        ->middleware('can_any:utility.view,dashboard.view');

    // CRUD Utility khusus pemegang permission `utility.manage`. Tabel `assets`
    // menampung mesin dan utility, jadi eksekusinya memakai MachineController
    // supaya validasi, audit log, dan perilaku soft delete tetap satu sumber.
    // Parameter bernama `machine` supaya aturan unik kode dan model binding
    // bawaan MachineController tetap bekerja.
    Route::middleware('can:utility.manage')->group(function () {
        Route::post('/utility', [MachineController::class, 'store'])->name('utility.store');
        Route::put('/utility/{machine}', [MachineController::class, 'update'])->name('utility.update');
        Route::delete('/utility/{machine}', [MachineController::class, 'destroy'])->name('utility.destroy');
        Route::patch('/utility/{machine}/restore', [MachineController::class, 'restore'])
            ->name('utility.restore')
            ->whereNumber('machine');
        // Edit komponen + standar per unit (pivot) dari halaman Utility.
        Route::put('/utility/{machine}/components', [MachineController::class, 'updateComponents'])
            ->name('utility.components.update');
    });

    // Suku Cadang: master suku cadang + transaksi stok (barang masuk/keluar).
    // Semua user yang login boleh melihat; kelola & unggah CSV hanya pemegang
    // `sparepart.manage` (Admin & tech-all).
    Route::get('/suku-cadang/dashboard', [SparePartDashboardController::class, 'index'])
        ->name('spareparts.dashboard')
        ->middleware('can:sparepart.view');
    Route::get('/suku-cadang', [SparePartController::class, 'index'])
        ->name('spareparts.index')
        ->middleware('can:sparepart.view');
    Route::get('/suku-cadang/{sparePart}/movements', [SparePartController::class, 'movements'])
        ->name('spareparts.movements')
        ->whereNumber('sparePart')
        ->middleware('can:sparepart.view');

    Route::middleware('can:sparepart.manage')->group(function () {
        Route::post('/suku-cadang', [SparePartController::class, 'store'])->name('spareparts.store');
        Route::post('/suku-cadang/import', [SparePartController::class, 'import'])->name('spareparts.import');
        // Template CSV untuk diisi lalu diunggah lewat route `import` di atas.
        Route::get('/suku-cadang/template', [SparePartController::class, 'template'])->name('spareparts.template');
        // Hapus massal: didaftarkan SEBELUM route {sparePart} supaya "bulk"
        // tidak ditangkap sebagai ID (lihat juga whereNumber di bawah).
        Route::delete('/suku-cadang/bulk', [SparePartController::class, 'bulkDestroy'])->name('spareparts.bulk-destroy');
        Route::put('/suku-cadang/{sparePart}', [SparePartController::class, 'update'])
            ->name('spareparts.update')
            ->whereNumber('sparePart');
        Route::delete('/suku-cadang/{sparePart}', [SparePartController::class, 'destroy'])
            ->name('spareparts.destroy')
            ->whereNumber('sparePart');
        Route::patch('/suku-cadang/{sparePart}/restore', [SparePartController::class, 'restore'])
            ->name('spareparts.restore')
            ->whereNumber('sparePart');
        Route::post('/suku-cadang/{sparePart}/movements', [SparePartController::class, 'storeMovement'])
            ->name('spareparts.movements.store')
            ->whereNumber('sparePart');
    });

    // Ekspor laporan PM. Halaman laporan lama (/reports) sudah dilebur ke
    // Beranda, jadi tinggal endpoint ekspor XLSX/PDF yang dipertahankan.
    Route::get('/dashboard/report/export', [ReportController::class, 'export'])
        ->name('dashboard.report.export')
        ->middleware('can:report.export');

    // Admin
    Route::middleware('can:user.manage')->group(function () {
        Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users');
        Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::put('/admin/users/{user}/password', [UserController::class, 'updatePassword'])->name('admin.users.update-password');
        Route::put('/admin/users/{user}/pin', [UserController::class, 'updatePin'])->name('admin.users.update-pin');
        Route::delete('/admin/users/{user}/pin', [UserController::class, 'destroyPin'])->name('admin.users.destroy-pin');
        Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });

    // PM Records Management (Admin)
    Route::middleware('can:pm.manage')->group(function () {
        Route::get('/admin/pm-records', [PmRecordController::class, 'adminIndex'])->name('admin.pm-records');
        Route::delete('/admin/pm-records/{record}', [PmRecordController::class, 'adminDestroy'])->name('admin.pm-records.destroy');
    });

    Route::middleware('role:admin')->group(function () {
        Route::resource('/admin/roles', RoleController::class)->names('admin.roles');
    });

    // Komponen / Parts: master komponen + penugasan ke Mesin & Utility.
    // Hanya Admin & tech-all (permission `component.manage`).
    Route::middleware('can:component.manage')->group(function () {
        Route::get('/admin/komponen', [ComponentController::class, 'index'])->name('admin.components');
        Route::post('/admin/komponen', [ComponentController::class, 'store'])->name('admin.components.store');
        Route::put('/admin/komponen/{component}', [ComponentController::class, 'update'])->name('admin.components.update');
        Route::delete('/admin/komponen/{component}', [ComponentController::class, 'destroy'])->name('admin.components.destroy');
        Route::post('/admin/komponen/penugasan', [ComponentController::class, 'assign'])->name('admin.components.assign');
    });

    // Integrate: email Microsoft 365 + fitur email. Hanya Admin.
    Route::middleware('can:integration.manage')->group(function () {
        Route::get('/admin/integrate', [IntegrationController::class, 'index'])->name('admin.integrate');
        Route::put('/admin/integrate', [IntegrationController::class, 'update'])->name('admin.integrate.update');
        Route::post('/admin/integrate/test', [IntegrationController::class, 'test'])
            ->middleware('throttle:6,1')
            ->name('admin.integrate.test');
    });

    // Master data Tipe Mesin & Lokasi / Site: hanya Admin & tech-all.
    Route::middleware('can:masterdata.manage')->group(function () {
        Route::get('/admin/tipe-mesin', [MachineTypeController::class, 'index'])->name('admin.machine-types');
        Route::post('/admin/tipe-mesin', [MachineTypeController::class, 'store'])->name('admin.machine-types.store');
        Route::put('/admin/tipe-mesin/{type}', [MachineTypeController::class, 'update'])->name('admin.machine-types.update');
        Route::delete('/admin/tipe-mesin/{type}', [MachineTypeController::class, 'destroy'])->name('admin.machine-types.destroy');

        Route::get('/admin/lokasi', [LocationController::class, 'index'])->name('admin.locations');
        Route::post('/admin/lokasi', [LocationController::class, 'store'])->name('admin.locations.store');
        Route::put('/admin/lokasi/{location}', [LocationController::class, 'update'])->name('admin.locations.update');
        Route::delete('/admin/lokasi/{location}', [LocationController::class, 'destroy'])->name('admin.locations.destroy');
    });

    Route::middleware('can:template.manage')->group(function () {
        // Template: kumpulan komponen untuk Mesin & Utility (Settings → Template).
        Route::get('/admin/templates', [TemplateController::class, 'index'])->name('admin.templates');
        Route::post('/admin/templates', [TemplateController::class, 'store'])->name('admin.templates.store');
        Route::put('/admin/templates/{template}', [TemplateController::class, 'update'])->name('admin.templates.update');
        Route::delete('/admin/templates/{template}', [TemplateController::class, 'destroy'])->name('admin.templates.destroy');
        Route::post('/admin/templates/{template}/terapkan', [TemplateController::class, 'apply'])->name('admin.templates.apply');
    });

    Route::middleware('can:activity.view')->group(function () {
        Route::get('/admin/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs');
    });

    // Backup & Restore database (khusus admin). Restore menimpa seluruh isi
    // database, jadi hanya dibuka untuk pemegang `backup.manage`.
    Route::middleware('can:backup.manage')->group(function () {
        Route::get('/admin/backups', [BackupController::class, 'index'])->name('admin.backups');
        Route::post('/admin/backups', [BackupController::class, 'store'])->name('admin.backups.store');
        Route::get('/admin/backups/{backup}/download', [BackupController::class, 'download'])->name('admin.backups.download');
        Route::post('/admin/backups/{backup}/restore', [BackupController::class, 'restore'])->name('admin.backups.restore');
        Route::post('/admin/backups/restore-upload', [BackupController::class, 'uploadRestore'])->name('admin.backups.upload');
        Route::delete('/admin/backups/{backup}', [BackupController::class, 'destroy'])->name('admin.backups.destroy');
    });
});

require __DIR__.'/auth.php';
