<?php

namespace App\Mail;

use App\Exports\PmReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Laporan PM otomatis (mingguan/bulanan) + lampiran Excel rekap PM.
 * Mingguan melampirkan periode berjalan; bulanan melampirkan setahun penuh.
 */
class PmReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  array<string, mixed>  $report */
    public function __construct(public readonly array $report) {}

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
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $weekly = $this->report['type'] === 'weekly';
        $period = $weekly ? $this->report['period'] : null;
        $name = $weekly
            ? "pm-report-{$this->report['year']}-{$this->report['period']}.xlsx"
            : "pm-report-{$this->report['year']}.xlsx";

        return [
            Attachment::fromData(
                fn () => Excel::raw(new PmReportExport($this->report['year'], $period), ExcelFormat::XLSX),
                $name,
            )->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
