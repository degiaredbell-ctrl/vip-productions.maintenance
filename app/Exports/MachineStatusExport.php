<?php

namespace App\Exports;

use App\Enums\Period;
use App\Services\DashboardService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ekspor daftar mesin + status PM sesuai filter Beranda.
 * Isinya berasal dari DashboardService supaya sama persis dengan tabel di layar.
 */
class MachineStatusExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private int $year,
        private Period $period,
        private string $status = 'all',
        private string $subCategory = 'all',
        private string $search = ''
    ) {}

    public function collection()
    {
        return DashboardService::applyFilters(
            DashboardService::machineStatuses($this->year, $this->period),
            $this->status,
            $this->subCategory,
            $this->search
        );
    }

    public function headings(): array
    {
        return [
            'Kode Mesin', 'Nama Mesin', 'Lokasi', 'Kategori', 'Sub Kategori',
            'Tipe', 'Minggu', 'Status', 'Periode', 'Tahun',
        ];
    }

    public function map($machine): array
    {
        return [
            $machine['code'],
            $machine['name'],
            $machine['location'],
            $machine['category'],
            $machine['sub_category'],
            $machine['type_label'],
            $machine['week_group'],
            $machine['status_label'],
            $this->period->label(),
            $this->year,
        ];
    }
}
