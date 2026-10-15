<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\AuditLogService;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Halaman "Backup & Restore" (khusus database, permission `backup.manage`).
 *
 * Backup dibuat dengan mysqldump ke storage privat; restore menjalankan file
 * .sql ke database lewat klien mysql. Karena restore menimpa seluruh isi
 * database, permintaan restore wajib menyertakan nama file sebagai konfirmasi
 * dan seluruh aksi penting dicatat ke Activity Log.
 */
class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups)
    {
    }

    public function index(): Response
    {
        $backups = Backup::with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Backup $backup) => [
                'id' => $backup->id,
                'filename' => $backup->filename,
                'size' => $backup->size,
                'human_size' => $backup->humanSize(),
                'type' => $backup->type,
                'type_label' => $backup->typeLabel(),
                'status' => $backup->status,
                'error' => $backup->error,
                'created_by' => $backup->user?->name ?? 'System',
                'created_at' => $backup->created_at?->format('d M Y H:i'),
                'exists' => Storage::disk($this->backups->disk())->exists($backup->filename),
            ]);

        $lastCompleted = Backup::query()->where('status', 'completed')->latest('created_at')->first();

        return Inertia::render('Admin/Backups', [
            'backups' => $backups,
            'stats' => [
                'total' => $backups->count(),
                'total_size' => $backups->sum('size'),
                'total_size_human' => $this->humanize((int) $backups->sum('size')),
                'last_at' => $lastCompleted?->created_at?->format('d M Y H:i'),
                'keep' => (int) config('backup.keep', 30),
                'schedule' => (string) config('backup.schedule', '02:00'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $backup = $this->backups->create('manual', $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', 'Backup gagal: '.$e->getMessage());
        }

        if ($backup->status === 'failed') {
            AuditLogService::record('backup.failed', Backup::class, $backup->id, [
                'filename' => $backup->filename,
            ]);

            return back()->with('error', 'Backup gagal: '.($backup->error ?? 'tidak diketahui'));
        }

        AuditLogService::record('backup.create', Backup::class, $backup->id, [
            'filename' => $backup->filename,
            'size' => $backup->size,
        ]);

        return back()->with('success', "Backup {$backup->filename} berhasil dibuat ({$backup->humanSize()}).");
    }

    public function download(Backup $backup)
    {
        $disk = Storage::disk($this->backups->disk());

        if (! $disk->exists($backup->filename)) {
            return back()->with('error', 'File backup tidak ditemukan di penyimpanan.');
        }

        return $disk->download($backup->filename, $backup->filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    public function restore(Request $request, Backup $backup): RedirectResponse
    {
        // Konfirmasi ganda: pemanggil harus mengetik ulang nama file. Mencegah
        // restore tak sengaja karena klik ganda atau URL yang dipanggil ulang.
        $request->validate(['confirm' => ['required', 'string']]);

        if ($request->input('confirm') !== $backup->filename) {
            throw ValidationException::withMessages([
                'confirm' => 'Nama file konfirmasi tidak cocok.',
            ]);
        }

        try {
            $this->backups->restore($backup);
            $this->forgetCaches();
        } catch (Throwable $e) {
            return back()->with('error', 'Restore gagal: '.$e->getMessage());
        }

        AuditLogService::record('backup.restore', Backup::class, $backup->id, [
            'filename' => $backup->filename,
            'source' => 'stored',
        ]);

        return back()->with('success', "Database dipulihkan dari {$backup->filename}.");
    }

    public function uploadRestore(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:512000'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Centang konfirmasi sebelum memulihkan database.',
        ]);

        $file = $request->file('file');

        if (strtolower($file->getClientOriginalExtension()) !== 'sql') {
            throw ValidationException::withMessages([
                'file' => 'Berkas harus berupa file .sql.',
            ]);
        }

        try {
            $this->backups->restorePath($file->getRealPath());
            $this->forgetCaches();
        } catch (Throwable $e) {
            return back()->with('error', 'Restore gagal: '.$e->getMessage());
        }

        AuditLogService::record('backup.restore', Backup::class, null, [
            'filename' => $file->getClientOriginalName(),
            'source' => 'upload',
        ]);

        return back()->with('success', "Database dipulihkan dari unggahan {$file->getClientOriginalName()}.");
    }

    public function destroy(Backup $backup): RedirectResponse
    {
        $filename = $backup->filename;

        $this->backups->delete($backup);

        AuditLogService::record('backup.delete', Backup::class, null, [
            'filename' => $filename,
        ]);

        return back()->with('success', "Backup {$filename} dihapus.");
    }

    /**
     * Restore mengganti isi tabel (termasuk cache), jadi cache lama dibuang
     * agar tidak ada data basi yang tersaji setelah pemulihan.
     */
    private function forgetCaches(): void
    {
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable $e) {
            // abaikan bila paket permission belum siap
        }

        try {
            \Illuminate\Support\Facades\Cache::flush();
        } catch (Throwable $e) {
            // abaikan bila cache store tidak mendukung flush
        }
    }

    private function humanize(int $size): string
    {
        return (new Backup(['size' => $size]))->humanSize();
    }
}
