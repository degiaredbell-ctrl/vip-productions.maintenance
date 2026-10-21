<?php

namespace App\Console\Commands;

use App\Services\Notifications\PmAlertDispatcher;
use Illuminate\Console\Command;

/**
 * Notifikasi PM terjadwal (Settings → Notifications):
 *
 *   php artisan notifications:pm-alert reminder   # Minggu ke-2 periode (sekali per periode)
 *   php artisan notifications:pm-alert overdue    # keterlambatan (07:00 & 14:00 WIB)
 *
 * `--dry-run` hanya menampilkan penerima & jumlah perangkat tanpa mengirim.
 * `--force` mengabaikan saklar, syarat Minggu ke-2, dan kunci anti-duplikat.
 */
class SendPmAlertCommand extends Command
{
    protected $signature = 'notifications:pm-alert
        {type : reminder atau overdue}
        {--dry-run : Tampilkan penerima tanpa mengirim}
        {--force : Kirim walau bukan jadwalnya / sudah pernah dikirim}';

    protected $description = 'Kirim notifikasi PM terjadwal (reminder Minggu ke-2 / keterlambatan)';

    public function handle(PmAlertDispatcher $dispatcher): int
    {
        $type = $this->argument('type');

        if (! in_array($type, ['reminder', 'overdue'], true)) {
            $this->error('Tipe harus reminder atau overdue.');

            return self::INVALID;
        }

        $result = $type === 'reminder'
            ? $dispatcher->reminder((bool) $this->option('force'), (bool) $this->option('dry-run'))
            : $dispatcher->overdue((bool) $this->option('force'), (bool) $this->option('dry-run'));

        $this->info($result['message']);

        if ($result['recipients'] !== []) {
            $this->table(
                ['Penerima', 'Email', 'Peran', 'Perangkat', 'Saluran'],
                collect($result['recipients'])->map(fn ($r) => [
                    $r['name'], $r['email'], implode(', ', $r['roles']), $r['count'], implode('+', $r['channels']) ?: '-',
                ])->all(),
            );
        }

        return self::SUCCESS;
    }
}
