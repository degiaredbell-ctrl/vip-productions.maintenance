<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database
                            {--type=manual : Jenis backup yang dicatat (manual|scheduled)}
                            {--prune : Hapus backup lama melebihi retensi setelah backup dibuat}';

    protected $description = 'Membuat backup database MySQL ke storage/app/backups';

    public function handle(BackupService $backups): int
    {
        try {
            $backup = $backups->create($this->option('type'));
        } catch (Throwable $e) {
            $this->error('Backup gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($backup->status === 'failed') {
            $this->error('Backup gagal: '.($backup->error ?? 'tidak diketahui'));

            return self::FAILURE;
        }

        $this->info("Backup dibuat: {$backup->filename} ({$backup->humanSize()})");

        if ($this->option('prune')) {
            $deleted = $backups->prune();
            $this->info("Prune selesai: {$deleted} backup lama dihapus.");
        }

        return self::SUCCESS;
    }
}
