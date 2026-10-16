<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ComponentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\PmRecordController;
use App\Http\Controllers\PmSignatureController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SparePartController;
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
    Route::get('/dashboard/export', [DashboardController::class, 'export'])
        ->name('dashboard.export')
        ->middleware('can:report.export');

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

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])
        ->name('reports.index')
        ->middleware('can:report.view');
    Route::get('/reports/export', [ReportController::class, 'export'])
        ->name('reports.export')
        ->middleware('can:report.export');

    // Admin
    Route::middleware('can:user.manage')->group(function () {
        Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users');
        Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::put('/admin/users/{user}/password', [UserController::class, 'updatePassword'])->name('admin.users.update-password');
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
    // Menggantikan halaman Template lama; permission `template.manage` dipakai
    // ulang supaya role yang sudah ada tidak perlu diatur ulang.
    Route::middleware('can:template.manage')->group(function () {
        Route::get('/admin/komponen', [ComponentController::class, 'index'])->name('admin.components');
        Route::post('/admin/komponen', [ComponentController::class, 'store'])->name('admin.components.store');
        Route::put('/admin/komponen/{component}', [ComponentController::class, 'update'])->name('admin.components.update');
        Route::delete('/admin/komponen/{component}', [ComponentController::class, 'destroy'])->name('admin.components.destroy');
        Route::post('/admin/komponen/penugasan', [ComponentController::class, 'assign'])->name('admin.components.assign');

        // URL lama diarahkan supaya bookmark/tautan lama tidak mati.
        Route::get('/admin/templates', fn () => redirect()->route('admin.components'));
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
