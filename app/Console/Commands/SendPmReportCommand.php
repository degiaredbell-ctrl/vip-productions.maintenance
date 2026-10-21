<?php

namespace App\Console\Commands;

use App\Mail\PmReportMail;
use App\Services\Integration\IntegrationSettings;
use App\Services\Integration\PmReportBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Kirim laporan PM otomatis ke penerima di Settings → Integrate.
 * Dijadwalkan di routes/console.php sesuai hari & jam di halaman Integrate.
 */
class SendPmReportCommand extends Command
{
    protected $signature = 'integration:send-pm-report
        {type=weekly : weekly atau monthly}
        {--to=* : Kirim ke alamat ini saja (uji coba), abaikan daftar penerima}';

    protected $description = 'Kirim laporan PM mingguan/bulanan lewat email Microsoft 365';

    public function handle(IntegrationSettings $settings): int
    {
        $type = $this->argument('type') === PmReportBuilder::MONTHLY ? PmReportBuilder::MONTHLY : PmReportBuilder::WEEKLY;

        if (! $settings->mailReady()) {
            $this->error('Koneksi email belum aktif. Atur di Settings → Integrate.');

            return self::FAILURE;
        }

        $recipients = $this->option('to')
            ? IntegrationSettings::parseRecipients(implode(',', $this->option('to')))
            : $settings->recipients('report.recipients');

        if ($recipients === []) {
            $this->error('Belum ada penerima laporan.');

            return self::FAILURE;
        }

        Mail::to($recipients)->send(new PmReportMail(PmReportBuilder::build($type)));

        $this->info('Laporan '.($type === PmReportBuilder::MONTHLY ? 'bulanan' : 'mingguan').' dikirim ke '.implode(', ', $recipients).'.');

        return self::SUCCESS;
    }
}
