<?php

namespace App\Console\Commands;

use App\Models\AssetType;
use App\Services\AuditLogService;
use App\Services\TemplateCsvImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportTemplatesCommand extends Command
{
    protected $signature = 'templates:import
        {file? : Path CSV (default references/data-source/templates/Template_Komponen_Mesin.csv)}
        {--dry-run : Tampilkan rencana tanpa mengubah database}
        {--no-apply : Buat/perbarui template saja, tanpa menerapkan ke unit}';

    protected $description = 'Impor Template komponen Mesin dari CSV lalu terapkan ke unit sesuai Code';

    public function handle(TemplateCsvImportService $importer): int
    {
        $path = $this->argument('file') ?: TemplateCsvImportService::defaultPath();
        $dryRun = (bool) $this->option('dry-run');
        $apply = ! $this->option('no-apply');

        try {
            $report = $importer->run($path, $dryRun, $apply);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Template', 'Tipe', 'Komponen', 'Unit', 'Status'],
            array_map(fn ($t) => [$t['name'], AssetType::labelFor((string) $t['type']), $t['components'], $t['units'], $t['status']], $report['templates']),
        );

        if ($report['reused'] !== []) {
            $this->line('Nama komponen yang disesuaikan:');
            foreach ($report['reused'] as $from => $to) {
                $this->line("  {$from} → {$to}");
            }
        }

        $this->line('Komponen baru di master ('.count($report['created']).'): '.implode(', ', $report['created']));

        foreach ($report['templates'] as $t) {
            foreach ($t['adjustments'] as $adj) {
                $this->line("Catatan CSV: {$adj}");
            }
        }

        if ($report['missingUnits'] !== []) {
            $this->warn('Unit tidak ditemukan: '.implode(', ', $report['missingUnits']));
        }

        $units = array_sum(array_column($report['templates'], 'units'));

        if ($dryRun) {
            $this->warn('Dry-run: tidak ada perubahan. Jalankan tanpa --dry-run untuk menyimpan.');

            return self::SUCCESS;
        }

        AuditLogService::record('template.import', null, null, [
            'file' => basename($path),
            'templates' => count($report['templates']),
            'components_created' => $report['created'],
            'units' => $apply ? $units : 0,
        ]);

        $this->info(count($report['templates']).' template disimpan'.($apply ? " dan diterapkan ke {$units} unit." : '.'));

        return self::SUCCESS;
    }
}
