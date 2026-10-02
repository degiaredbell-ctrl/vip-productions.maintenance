<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\PmRecordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // PM Records
    Route::get('/machines/{machine}/pm', [PmRecordController::class, 'create'])
        ->name('machines.pm.create')
        ->middleware('can:pm.fill,dashboard.view');
    Route::post('/machines/{machine}/pm', [PmRecordController::class, 'store'])
        ->name('machines.pm.store')
        ->middleware('can:pm.fill');
    Route::post('/pm/{record}/approve', [PmRecordController::class, 'approve'])
        ->name('pm.approve')
        ->middleware('can:pm.approve');

    // Machines
    Route::get('/machines', [MachineController::class, 'index'])
        ->name('machines.index')
        ->middleware('can:machine.manage');
    Route::post('/machines', [MachineController::class, 'store'])
        ->name('machines.store')
        ->middleware('can:machine.manage');
    Route::put('/machines/{machine}', [MachineController::class, 'update'])
        ->name('machines.update')
        ->middleware('can:machine.manage');
    Route::delete('/machines/{machine}', [MachineController::class, 'destroy'])
        ->name('machines.destroy')
        ->middleware('can:machine.manage');
    Route::get('/machines/{machine}/history', [MachineController::class, 'history'])
        ->name('machines.history')
        ->middleware('can:pm.history.view');

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
        Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });

    Route::middleware('can:template.manage')->group(function () {
        Route::get('/admin/templates', [TemplateController::class, 'index'])->name('admin.templates');
        Route::post('/admin/templates', [TemplateController::class, 'store'])->name('admin.templates.store');
        Route::delete('/admin/templates/{template}', [TemplateController::class, 'destroy'])->name('admin.templates.destroy');
    });

    Route::middleware('can:audit.view')->group(function () {
        Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs');
    });
});

require __DIR__.'/auth.php';
