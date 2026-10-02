<?php

namespace App\Console\Commands;

use App\Services\MachineImportService;
use Illuminate\Console\Command;

class ImportMachinesCommand extends Command
{
    protected $signature = 'machines:import
        {--purge : Hapus permanen mesin yang kodenya tidak ada di CSV (riwayat PM ikut terhapus)}
        {--force : Lewati konfirmasi, untuk dijalankan non-interaktif}';

    protected $description = 'Impor daftar mesin dari references/data.csv';

    public function handle(MachineImportService $importer): int
    {
        if (!$importer->exists()) {
            $this->error("File CSV tidak ditemukan: {$importer->csvPath()}");

            return self::FAILURE;
        }

        $purge = (bool) $this->option('purge');

        if ($purge && !$this->option('force') && !$this->confirm('Hapus permanen semua mesin lama yang tidak ada di CSV? Riwayat PM mesin tersebut ikut terhapus dan tidak bisa dibatalkan.', false)) {
            $this->warn('Dibatalkan, tidak ada perubahan.');

            return self::SUCCESS;
        }

        $report = $importer->import(purge: $purge);

        $this->newLine();
        $this->info("Impor selesai. Total baris CSV: {$report['total']}");
        $this->table(
            ['Aksi', 'Jumlah'],
            [
                ['Mesin baru', $report['created']],
                ['Mesin diperbarui', $report['updated']],
                ['Mesin dihapus (--purge)', $report['purged']],
            ]
        );

        $this->line('Jenis mesin (ditentukan dari nama):');
        $this->table(
            ['Jenis', 'Jumlah'],
            array_map(
                fn ($type, $count) => [\App\Enums\MachineType::tryFrom($type)?->label() ?? $type, $count],
                array_keys($report['types']),
                $report['types']
            )
        );

        $this->line('Pengelompokan minggu (dari Sub-Category):');
        $this->table(
            ['Minggu', 'Jumlah'],
            array_map(fn ($week, $count) => ["Minggu {$week}", $count], array_keys($report['weeks']), $report['weeks'])
        );

        return self::SUCCESS;
    }
}
