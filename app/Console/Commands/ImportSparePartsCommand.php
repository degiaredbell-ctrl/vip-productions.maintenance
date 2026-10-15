<?php

namespace App\Console\Commands;

use App\Services\SparePartImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportSparePartsCommand extends Command
{
    protected $signature = 'spare-parts:import
        {--file= : Path CSV lain (default references/data-source/suku_cadang.csv)}
        {--purge : Hapus permanen suku cadang yang kodenya tidak ada di CSV}
        {--force : Lewati konfirmasi, untuk dijalankan non-interaktif}';

    protected $description = 'Impor master suku cadang dari references/data-source/suku_cadang.csv';

    public function handle(SparePartImportService $importer): int
    {
        $path = (string) ($this->option('file') ?: $importer->defaultPath());

        if (! is_file($path)) {
            $this->error("File CSV tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $purge = (bool) $this->option('purge');

        if ($purge && ! $this->option('force') && ! $this->confirm(
            'Hapus permanen semua suku cadang lama yang tidak ada di CSV? Riwayat transaksi stoknya ikut terhapus.',
            false
        )) {
            $this->warn('Dibatalkan, tidak ada perubahan.');

            return self::SUCCESS;
        }

        try {
            $report = $importer->importFromPath($path, $purge);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Impor selesai. File: {$report['path']}");
        $this->table(
            ['Aksi', 'Jumlah'],
            [
                ['Suku cadang baru', $report['created']],
                ['Suku cadang diperbarui', $report['updated']],
                ['Suku cadang dihapus (--purge)', $report['purged']],
                ['Baris dilewati', $report['skipped']],
                ['Total baris terbaca', $report['total']],
            ]
        );

        return self::SUCCESS;
    }
}
