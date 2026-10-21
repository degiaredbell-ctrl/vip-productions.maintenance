<?php

namespace App\Services\Notifications;

use App\Mail\PmReportMail;
use App\Models\NotificationDispatch;
use App\Models\User;
use App\Services\Integration\IntegrationSettings;
use App\Services\Integration\MailConfigurator;
use App\Services\Integration\PmReportBuilder;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Laporan mingguan / bulanan (matriks no. 4, khusus Email).
 * Penerima: Atasan (otomatis, bila diaktifkan) + email tambahan.
 */
class PmReportSender
{
    public function __construct(private readonly IntegrationSettings $settings) {}

    /** @return list<string> */
    public function recipients(): array
    {
        $supervisors = $this->settings->bool('report.to_supervisor')
            ? NotificationRecipients::supervisors()->pluck('email')->all()
            : [];

        return IntegrationSettings::parseRecipients(implode(',', [...$supervisors, ...$this->settings->recipients('report.recipients')]));
    }

    /**
     * @param  list<string>|null  $to  Kirim ke alamat ini saja (uji coba).
     * @return list<string> Alamat yang dikirimi.
     */
    public function send(string $type, ?array $to = null, ?User $by = null): array
    {
        $type = $type === PmReportBuilder::MONTHLY ? PmReportBuilder::MONTHLY : PmReportBuilder::WEEKLY;

        if (! $this->settings->mailReady()) {
            throw new RuntimeException('Koneksi email belum aktif. Atur di Settings → Integrate.');
        }

        $recipients = $to ?: $this->recipients();
        if ($recipients === []) {
            throw new RuntimeException('Belum ada penerima laporan (Atasan tanpa email valid dan tidak ada email tambahan).');
        }

        MailConfigurator::apply($this->settings);

        $report = PmReportBuilder::build($type);
        Mail::to($recipients)->send(new PmReportMail(
            $report,
            $this->settings->bool('report.attach_pdf'),
            $this->settings->bool('report.attach_excel'),
        ));

        NotificationDispatch::create([
            'type' => "report.{$type}",
            'trigger' => $by ? 'manual' : 'schedule',
            'summary' => mb_substr("{$report['title']} · {$report['rangeLabel']} → ".implode(', ', $recipients), 0, 500),
            'recipients' => count($recipients),
            'email_sent' => count($recipients),
            'items' => $report['summary']['total'],
            'triggered_by' => $by?->id,
            'created_at' => now(),
        ]);

        return $recipients;
    }
}
