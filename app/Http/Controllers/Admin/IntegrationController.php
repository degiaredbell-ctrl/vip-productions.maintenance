<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PmReportMail;
use App\Services\AuditLogService;
use App\Services\Integration\IntegrationSettings;
use App\Services\Integration\MailConfigurator;
use App\Services\Integration\PmReportBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Settings → Integrate: koneksi email Microsoft 365 perusahaan dan fitur yang
 * memakainya (lupa password, verifikasi login OTP, notifikasi, laporan).
 * Hanya Admin (permission `integration.manage`).
 */
class IntegrationController extends Controller
{
    public function __construct(private readonly IntegrationSettings $settings) {}

    public function index(Request $request): Response
    {
        $s = $this->settings;

        return Inertia::render('Admin/Integrate', [
            'settings' => [
                'mail_enabled' => $s->bool('mail.enabled'),
                'mail_driver' => $s->get('mail.driver'),
                'from_address' => $s->get('mail.from_address'),
                'from_name' => $s->get('mail.from_name'),
                'graph_tenant_id' => $s->get('graph.tenant_id'),
                'graph_client_id' => $s->get('graph.client_id'),
                'graph_client_secret_set' => $s->hasSecret('graph.client_secret'),
                'smtp_host' => $s->get('smtp.host'),
                'smtp_port' => (int) $s->get('smtp.port'),
                'smtp_encryption' => $s->get('smtp.encryption'),
                'smtp_username' => $s->get('smtp.username'),
                'smtp_password_set' => $s->hasSecret('smtp.password'),
                'feature_password_reset' => $s->bool('feature.password_reset'),
                'feature_login_otp' => $s->bool('feature.login_otp'),
                'login_otp_remember_days' => (int) $s->get('feature.login_otp_remember_days'),
                'notify_pm_awaiting' => $s->bool('notify.pm_awaiting'),
                'notify_pm_rejected' => $s->bool('notify.pm_rejected'),
                'notify_pm_approved' => $s->bool('notify.pm_approved'),
                'notify_low_stock' => $s->bool('notify.low_stock'),
                'low_stock_recipients' => $s->get('notify.low_stock_recipients'),
                'report_recipients' => $s->get('report.recipients'),
                'report_weekly_enabled' => $s->bool('report.weekly_enabled'),
                'report_weekly_day' => (int) $s->get('report.weekly_day'),
                'report_weekly_time' => $s->get('report.weekly_time'),
                'report_monthly_enabled' => $s->bool('report.monthly_enabled'),
                'report_monthly_day' => (int) $s->get('report.monthly_day'),
                'report_monthly_time' => $s->get('report.monthly_time'),
            ],
            'status' => [
                'ready' => $s->mailReady(),
                'last_test_at' => $s->get('mail.last_test_at'),
                'last_test_ok' => $s->get('mail.last_test_ok') === null ? null : (string) $s->get('mail.last_test_ok') === '1',
                'last_test_error' => $s->get('mail.last_test_error'),
            ],
            'defaultTestRecipient' => $request->user()->email,
            'appUrl' => config('app.url'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_enabled' => ['boolean'],
            'mail_driver' => ['required', Rule::in(['graph', 'smtp'])],
            'from_address' => ['nullable', 'email', 'max:255', Rule::requiredIf($request->boolean('mail_enabled'))],
            'from_name' => ['nullable', 'string', 'max:100'],
            'graph_tenant_id' => ['nullable', 'string', 'max:100', Rule::requiredIf($request->boolean('mail_enabled') && $request->input('mail_driver') === 'graph')],
            'graph_client_id' => ['nullable', 'string', 'max:100', Rule::requiredIf($request->boolean('mail_enabled') && $request->input('mail_driver') === 'graph')],
            'graph_client_secret' => ['nullable', 'string', 'max:500'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'feature_password_reset' => ['boolean'],
            'feature_login_otp' => ['boolean'],
            'login_otp_remember_days' => ['required', 'integer', 'between:1,365'],
            'notify_pm_awaiting' => ['boolean'],
            'notify_pm_rejected' => ['boolean'],
            'notify_pm_approved' => ['boolean'],
            'notify_low_stock' => ['boolean'],
            'low_stock_recipients' => ['nullable', 'string', 'max:2000'],
            'report_recipients' => ['nullable', 'string', 'max:2000'],
            'report_weekly_enabled' => ['boolean'],
            'report_weekly_day' => ['required', 'integer', 'between:1,7'],
            'report_weekly_time' => ['required', 'date_format:H:i'],
            'report_monthly_enabled' => ['boolean'],
            'report_monthly_day' => ['required', 'integer', 'between:1,28'],
            'report_monthly_time' => ['required', 'date_format:H:i'],
        ], [
            'from_address.required' => 'Isi email pengirim (mailbox Microsoft 365).',
            'graph_tenant_id.required' => 'Isi Tenant ID (Directory ID) dari Microsoft Entra.',
            'graph_client_id.required' => 'Isi Client ID (Application ID) dari app registration.',
        ]);

        $enabling = $request->boolean('mail_enabled');

        if ($enabling && $data['mail_driver'] === 'graph' && blank($data['graph_client_secret'] ?? null) && ! $this->settings->hasSecret('graph.client_secret')) {
            return back()->withErrors(['graph_client_secret' => 'Isi Client Secret dari app registration.']);
        }

        if ((($data['report_weekly_enabled'] ?? false) || ($data['report_monthly_enabled'] ?? false)) && IntegrationSettings::parseRecipients((string) ($data['report_recipients'] ?? '')) === []) {
            return back()->withErrors(['report_recipients' => 'Isi minimal satu email penerima laporan.']);
        }

        $values = [
            'mail.enabled' => $enabling,
            'mail.driver' => $data['mail_driver'],
            'mail.from_address' => trim((string) ($data['from_address'] ?? '')),
            'mail.from_name' => trim((string) ($data['from_name'] ?? '')) ?: 'PM Preventive',
            'graph.tenant_id' => trim((string) ($data['graph_tenant_id'] ?? '')),
            'graph.client_id' => trim((string) ($data['graph_client_id'] ?? '')),
            'smtp.host' => trim((string) ($data['smtp_host'] ?? '')) ?: 'smtp.office365.com',
            'smtp.port' => (int) ($data['smtp_port'] ?? 587),
            'smtp.encryption' => $data['smtp_encryption'] ?? 'tls',
            'smtp.username' => trim((string) ($data['smtp_username'] ?? '')),
            'feature.password_reset' => $request->boolean('feature_password_reset'),
            'feature.login_otp' => $request->boolean('feature_login_otp'),
            'feature.login_otp_remember_days' => (int) $data['login_otp_remember_days'],
            'notify.pm_awaiting' => $request->boolean('notify_pm_awaiting'),
            'notify.pm_rejected' => $request->boolean('notify_pm_rejected'),
            'notify.pm_approved' => $request->boolean('notify_pm_approved'),
            'notify.low_stock' => $request->boolean('notify_low_stock'),
            'notify.low_stock_recipients' => implode(', ', IntegrationSettings::parseRecipients((string) ($data['low_stock_recipients'] ?? ''))),
            'report.recipients' => implode(', ', IntegrationSettings::parseRecipients((string) ($data['report_recipients'] ?? ''))),
            'report.weekly_enabled' => $request->boolean('report_weekly_enabled'),
            'report.weekly_day' => (int) $data['report_weekly_day'],
            'report.weekly_time' => $data['report_weekly_time'],
            'report.monthly_enabled' => $request->boolean('report_monthly_enabled'),
            'report.monthly_day' => (int) $data['report_monthly_day'],
            'report.monthly_time' => $data['report_monthly_time'],
        ];

        // Rahasia hanya diganti bila diisi; kosong = pertahankan yang lama.
        if (filled($data['graph_client_secret'] ?? null)) {
            $values['graph.client_secret'] = $data['graph_client_secret'];
        }
        if (filled($data['smtp_password'] ?? null)) {
            $values['smtp.password'] = $data['smtp_password'];
        }

        $this->settings->set($values);

        AuditLogService::record('integration.update', null, null, [
            'mail_enabled' => $values['mail.enabled'],
            'driver' => $values['mail.driver'],
            'from' => $values['mail.from_address'],
            'login_otp' => $values['feature.login_otp'],
            'password_reset' => $values['feature.password_reset'],
            'secret_changed' => isset($values['graph.client_secret']) || isset($values['smtp.password']),
        ]);

        return back()->with('success', 'Pengaturan Integrate tersimpan.');
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email']], ['to.required' => 'Isi email tujuan uji.']);

        if (! $this->settings->mailReady()) {
            return back()->with('error', 'Aktifkan dan simpan koneksi email terlebih dahulu.');
        }

        MailConfigurator::apply($this->settings);

        try {
            Mail::raw(
                "Email uji dari PM Preventive — PT Verra Inter Pangan.\n\n"
                ."Jika Anda menerima email ini, integrasi email Microsoft 365 sudah berfungsi.\n"
                .'Metode: '.($this->settings->get('mail.driver') === 'smtp' ? 'SMTP' : 'Microsoft Graph API')."\n"
                .'Dikirim: '.now()->format('d/m/Y H:i:s').' WIB',
                fn (Message $m) => $m->to($data['to'])->subject('Uji koneksi email — PM Preventive'),
            );
        } catch (Throwable $e) {
            report($e);
            $this->recordTest(false, $e->getMessage());

            return back()->with('error', 'Email uji gagal: '.$e->getMessage());
        }

        $this->recordTest(true, null);

        return back()->with('success', "Email uji terkirim ke {$data['to']}. Periksa kotak masuk (dan folder Junk).");
    }

    public function sendReport(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([PmReportBuilder::WEEKLY, PmReportBuilder::MONTHLY])],
        ]);

        if (! $this->settings->mailReady()) {
            return back()->with('error', 'Aktifkan dan simpan koneksi email terlebih dahulu.');
        }

        $recipients = $this->settings->recipients('report.recipients') ?: [$request->user()->email];

        MailConfigurator::apply($this->settings);

        try {
            Mail::to($recipients)->send(new PmReportMail(PmReportBuilder::build($data['type'])));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Laporan gagal dikirim: '.$e->getMessage());
        }

        return back()->with('success', 'Laporan '.($data['type'] === PmReportBuilder::MONTHLY ? 'bulanan' : 'mingguan').' dikirim ke '.implode(', ', $recipients).'.');
    }

    private function recordTest(bool $ok, ?string $error): void
    {
        $this->settings->set([
            'mail.last_test_at' => now()->format('d/m/Y H:i'),
            'mail.last_test_ok' => $ok ? '1' : '0',
            'mail.last_test_error' => $error ? mb_substr($error, 0, 500) : null,
        ]);
    }
}
