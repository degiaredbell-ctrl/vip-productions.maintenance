<?php

namespace App\Services;

use App\Enums\MachineType;
use App\Enums\Period;
use App\Enums\PmDisplayStatus;
use App\Models\Machine;
use Illuminate\Support\Collection;

/**
 * Sumber tunggal data Beranda: daftar mesin + status PM pada satu periode,
 * ringkasannya, dan filter (area/status/pencarian).
 *
 * Dipakai oleh DashboardController@index maupun ekspor xlsx/pdf supaya tabel
 * yang tampil di layar dan yang tercetak tidak mungkin berbeda.
 */
class DashboardService
{
    /**
     * Seluruh mesin aktif pada satu periode, lengkap dengan status tampilan PM.
     * Statistik Beranda memakai koleksi ini sebelum difilter supaya angka
     * ringkasan tidak berubah saat user menyaring daftar.
     */
    public static function machineStatuses(int $year, Period $period): Collection
    {
        return Machine::with(['template.items', 'pmRecords' => function ($q) use ($year, $period) {
            $q->where('year', $year)->where('period', $period->value);
        }])
            ->where('is_active', true)
            // Utility dikelola lewat halaman khusus, bukan Beranda, jadi tidak
            // ikut dihitung pada daftar maupun statistik PM.
            ->where('type', '!=', MachineType::Utility->value)
            ->orderBy('sort_no')
            ->get()
            ->map(function (Machine $machine) {
                $record = $machine->pmRecords->first();

                $display = $record
                    ? PmDisplayStatus::fromPmStatus($record->status)
                    : PmDisplayStatus::Todo;

                return [
                    'id' => $machine->id,
                    'code' => $machine->code,
                    'name' => $machine->name,
                    'location' => $machine->location,
                    'category' => $machine->category,
                    'sub_category' => $machine->sub_category,
                    'type' => $machine->type,
                    'type_label' => MachineType::tryFrom($machine->type)?->label() ?? $machine->type,
                    'week_group' => $machine->week_group,
                    'status' => $display->value,
                    'status_label' => $display->label(),
                    'record_id' => $record?->id,
                    'record_status' => $record?->status->value,
                    // "On Progress Approval by ..." hanya muncul saat PM
                    // benar-benar di rantai approval, bukan saat belum dikerjakan.
                    'progress_note' => $record?->status->isInApproval()
                        ? $record->status->label()
                        : null,
                ];
            })
            ->values();
    }

    public static function stats(Collection $machines): array
    {
        return [
            'total' => $machines->count(),
            'done' => $machines->where('status', PmDisplayStatus::Done->value)->count(),
            'progress' => $machines->where('status', PmDisplayStatus::Progress->value)->count(),
            'todo' => $machines->where('status', PmDisplayStatus::Todo->value)->count(),
            'issue' => $machines->where('status', PmDisplayStatus::Issue->value)->count(),
        ];
    }

    /**
     * Daftar area (sub_category) yang tersedia, dipakai filter Beranda.
     */
    public static function subCategories(): Collection
    {
        return Machine::where('is_active', true)
            ->where('type', '!=', MachineType::Utility->value)
            ->whereNotNull('sub_category')
            ->distinct()
            ->orderBy('sub_category')
            ->pluck('sub_category')
            ->values();
    }

    /**
     * Filter yang sama dipakai di halaman Beranda maupun saat ekspor:
     * area (C.1 s.d. C.9), status PM, dan pencarian bebas.
     */
    public static function applyFilters(
        Collection $machines,
        string $status = 'all',
        string $subCategory = 'all',
        string $search = ''
    ): Collection {
        $needle = mb_strtolower(trim($search));

        return $machines
            ->when($subCategory !== 'all', fn ($c) => $c->where('sub_category', $subCategory))
            ->when($status !== 'all', fn ($c) => $c->where('status', $status))
            ->when($needle !== '', fn ($c) => $c->filter(fn ($m) => str_contains(mb_strtolower($m['code']), $needle)
                || str_contains(mb_strtolower($m['name']), $needle)
                // Sub-category dan lokasi ikut dicari supaya "C.7" atau
                // "Milenium" bisa dipakai sebagai kata kunci.
                || str_contains(mb_strtolower((string) $m['sub_category']), $needle)
                || str_contains(mb_strtolower((string) $m['location']), $needle)
            ))
            ->values();
    }
}
