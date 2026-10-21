<?php

namespace App\Console\Commands;

use App\Services\Integration\IntegrationSettings;
use App\Services\Integration\PmReportBuilder;
use App\Services\Notifications\PmReportSender;
use Illuminate\Console\Command;
use Throwable;

/**
 * Kirim laporan PM otomatis ke Atasan (+ email tambahan) sesuai Settings →
 * Notifications. Dijadwalkan di routes/console.php.
 */
class SendPmReportCommand extends Command
{
    protected $signature = 'integration:send-pm-report
        {type=weekly : weekly atau monthly}
        {--to=* : Kirim ke alamat ini saja (uji coba), abaikan daftar penerima}';

    protected $description = 'Kirim laporan PM mingguan/bulanan lewat email Microsoft 365';

    public function handle(PmReportSender $sender): int
    {
        $type = $this->argument('type') === PmReportBuilder::MONTHLY ? PmReportBuilder::MONTHLY : PmReportBuilder::WEEKLY;
        $to = $this->option('to') ? IntegrationSettings::parseRecipients(implode(',', $this->option('to'))) : null;

        try {
            $recipients = $sender->send($type, $to);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Laporan '.($type === PmReportBuilder::MONTHLY ? 'bulanan' : 'mingguan').' dikirim ke '.implode(', ', $recipients).'.');

        return self::SUCCESS;
    }
}
