<?php

namespace App\Exports;

use App\Enums\Period;
use App\Enums\SignatureStage;
use App\Models\PmRecord;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PmReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private int $year,
        private ?string $period = null
    ) {}

    public function collection()
    {
        return PmRecord::with(['machine', 'technician', 'items', 'signatures'])
            ->where('year', $this->year)
            ->when($this->period, function ($q) {
                $q->where('period', $this->period);
            })
            ->get();
    }

    public function headings(): array
    {
        return [
            'Kode Mesin', 'Nama Mesin', 'Periode', 'Tahun', 'Status',
            'Teknisi', 'Tanggal Submit', 'Tanggal Approve', 'Jumlah Revisi',
            'TT Teknisi', 'TT User PIC', 'TT Atasan', 'Alasan Penolakan',
        ];
    }

    public function map($record): array
    {
        return [
            $record->machine->code,
            $record->machine->name,
            Period::tryFrom($record->period)?->label() ?? $record->period,
            $record->year,
            $record->status->label(),
            $record->technicianName(),
            $record->submitted_at?->format('d/m/Y'),
            $record->approved_at?->format('d/m/Y'),
            $record->revision_count,
            ...$this->signatureColumns($record),
        ];
    }

    /**
     * Satu kolom per tahap tanda tangan: siapa, kapan, dan catatan penolakan
     * bila ada. Kolom "TT" dibuat setelah kolom lama supaya format sheet yang
     * sudah dipakai tidak berubah posisi kolomnya.
     */
    private function signatureColumns(PmRecord $record): array
    {
        $columns = [];

        foreach (SignatureStage::ordered() as $stage) {
            $signature = $record->signatures->firstWhere('stage', $stage);
            $columns[] = $signature
                ? sprintf('%s — %s', $signature->signed_by_name, $signature->signed_at->format('d/m/Y H:i'))
                : '-';
        }

        $columns[] = $record->rejectReason();

        return $columns;
    }
}
