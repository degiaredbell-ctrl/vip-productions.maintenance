<?php

namespace App\Console\Commands;

use App\Models\PmRecord;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membersihkan sisa data uji E2E tanpa menyentuh data produksi.
 *
 * Command ini dibuat setelah satu kesalahan: folder tanda tangan milik record
 * produksi ikut terhapus karena cleanup cukup mencocokkan nama folder dengan
 * daftar id hasil E2E. Karena itu file di sini hanya dihapus lewat baris gambar
 * milik record yang sedang dihapus, bukan lewat tebakan nama folder.
 *
 * Default-nya dry-run; harus ada --force untuk benar-benar menghapus.
 */
class CleanupE2eDataCommand extends Command
{
    protected $signature = 'e2e:cleanup
        {--pattern=e2e% : Awalan email akun uji yang boleh dihapus (tanpa %)}
        {--force : Jalankan penghapusan tanpa konfirmasi}';

    protected $description = 'Hapus akun dan checklist uji E2E beserta file tandatangannya';

    /**
     * Record yang belum pernah ditandatangani orang lain, jadi menghapus
     *试样 tidak menghilangkan persetujuan apa pun.
     */
    private const SAFE_STATUSES = ['draft', 'submitted', 'rejected'];

    /**
     * Record yang sudah ada tanda tangan manusia. Dibiarkan dan hanya
     * dilaporkan supaya tidak hilang tanpa pernah disetujui siapa pun.
     */
    private const REPORT_ONLY_STATUSES = ['pic_approved', 'approved'];

    public function handle(): int
    {
        $pattern = (string) $this->option('pattern');

        $users = User::where('email', 'like', $pattern)->get();
        $records = $this->e2eRecords($users->pluck('id'), self::SAFE_STATUSES)->get();
        $signedRecords = $this->e2eRecords($users->pluck('id'), self::REPORT_ONLY_STATUSES)->get();

        if ($users->isEmpty() && $records->isEmpty() && $signedRecords->isEmpty()) {
            $this->info("Tidak ada data uji dengan pola '{$pattern}'.");

            return self::SUCCESS;
        }

        $this->table(
            ['Jenis', 'Jumlah', 'Rincian'],
            [
                ['Akun uji', $users->count(), $users->pluck('email')->implode(', ') ?: '-'],
                ['Checklist uji (aman dihapus)', $records->count(), $this->describe($records)],
            ],
        );

        if ($signedRecords->isNotEmpty()) {
            $this->warn('Record uji berikut TIDAK dihapus karena sudah ada tanda tangan manusia:');
            $this->line('  '.$this->describe($signedRecords));
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->comment('Mode dry-run. Ulangi dengan --force untuk benar-benar menghapus.');

            return self::SUCCESS;
        }

        $this->newLine();

        foreach ($records as $record) {
            $this->deleteSignatureFiles($record);
            $record->delete();
            $this->line("  checklist #{$record->id} dihapus");
        }

        foreach ($users as $user) {
            $user->roles()->detach();
            $user->delete();
            $this->line("  akun {$user->email} dihapus");
        }

        // Role Spatie disimpan di cache; tanpa ini role yang sudah dilepas masih
        // terbaca di request berikutnya.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Pembersihan selesai.');

        return self::SUCCESS;
    }

    /**
     * Query record milik akun uji dengan status tertentu.
     *
     * Dicocokkan lewat `technician_id` yang menunjuk akun uji. Nama teknisi
     * tidak dipakai sebagai kunci karena diketik bebas di form, sedangkan id
     * akun selalu pasti milik E2E.
     *
     * @param  Collection<int, int>  $technicianIds
     * @return Builder<PmRecord>
     */
    private function e2eRecords(Collection $technicianIds, array $statuses): Builder
    {
        return PmRecord::query()
            ->with('picUser')
            ->whereIn('technician_id', $technicianIds)
            ->whereIn('status', $statuses)
            ->orderByDesc('id');
    }

    /** @param  Collection<int, PmRecord>  $records */
    private function describe(Collection $records): string
    {
        if ($records->isEmpty()) {
            return '-';
        }

        return $records
            ->map(function (PmRecord $record) {
                $pic = $record->picUser?->name ?? '-';

                return "#{$record->id} ({$record->status->value}, PIC: {$pic})";
            })
            ->implode('; ');
    }

    /**
     * Hapus file tanda tangan milik record ini saja.
     *
     * Nama file dibaca dari baris `pm_signatures`, bukan dari pola folder. Dengan
     * begitu file produksi tidak mungkin terhapus hanya karena id-nya kebetulan
     * ada di daftar E2E.
     */
    private function deleteSignatureFiles(PmRecord $record): void
    {
        $disk = Storage::disk('public');

        foreach ($record->signatures as $signature) {
            if (! $signature->image_path || ! $disk->exists($signature->image_path)) {
                continue;
            }

            if (! $disk->delete($signature->image_path)) {
                // Conversion::disk biasanya menelan error jadi false. Dilaporkan
                // karena sisa file yatim tidak terlihat dari database.
                $this->warn("  gagal hapus {$signature->image_path} (izin tulis?)");
            }
        }

        if ($disk->exists("signatures/pm-record-{$record->id}")) {
            $disk->deleteDirectory("signatures/pm-record-{$record->id}");
            if ($disk->exists("signatures/pm-record-{$record->id}")) {
                $this->warn("  gagal hapus folder signatures/pm-record-{$record->id} (izin tulis?)");
            }
        }
    }
}