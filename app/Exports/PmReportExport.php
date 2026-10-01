<?php

namespace App\Exports;

use App\Enums\Period;
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
        return PmRecord::with(['machine', 'technician', 'items'])
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
            $record->technician?->name,
            $record->submitted_at?->format('d/m/Y'),
            $record->approved_at?->format('d/m/Y'),
            $record->revision_count,
        ];
    }
}
