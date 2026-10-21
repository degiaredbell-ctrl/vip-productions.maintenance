<?php

namespace App\Mail;

use App\Exports\PmReportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Laporan PM otomatis (mingguan/bulanan) untuk Atasan.
 *
 * Lampiran (bisa diatur di Settings → Notifications):
 * - PDF ringkasan: angka pencapaian + daftar perangkat terlambat.
 * - Excel rekap PM: mingguan = periode berjalan; bulanan = setahun penuh.
 */
class PmReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  array<string, mixed>  $report */
    public function __construct(
        public readonly array $report,
        public readonly bool $attachPdf = true,
        public readonly bool $attachExcel = true,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->report['title']} — {$this->report['rangeLabel']}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.pm-report', with: [
            'report' => $this->report,
            'url' => route('dashboard'),
            'base' => rtrim((string) config('app.url'), '/'),
            'attachPdf' => $this->attachPdf,
            'attachExcel' => $this->attachExcel,
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $weekly = $this->report['type'] === 'weekly';
        $slug = $weekly ? "{$this->report['year']}-{$this->report['period']}" : (string) $this->report['year'];
        $attachments = [];

        if ($this->attachPdf) {
            $attachments[] = Attachment::fromData(
                fn () => Pdf::loadView('reports.notification-report', ['report' => $this->report])
                    ->setPaper('a4', 'portrait')
                    ->output(),
                "laporan-pm-{$this->report['type']}-".now()->format('Ymd').'.pdf',
            )->withMime('application/pdf');
        }

        if ($this->attachExcel) {
            $attachments[] = Attachment::fromData(
                fn () => Excel::raw(new PmReportExport($this->report['year'], $weekly ? $this->report['period'] : null), ExcelFormat::XLSX),
                "pm-report-{$slug}.xlsx",
            )->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }

        return $attachments;
    }
}
