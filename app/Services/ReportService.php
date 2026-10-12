<?php

namespace App\Services;

use App\Enums\Period;
use App\Models\PmRecord;
use App\Models\PmRecordItem;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public static function complianceByPeriod(int $year): array
    {
        // Dihitung dalam satu query grouped, bukan looping COUNT per periode,
        // supaya laporan tidak menghasilkan 12 query untuk 6 periode.
        $rows = PmRecord::where('year', $year)
            ->selectRaw('period, COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS completed")
            ->selectRaw("SUM(CASE WHEN status IN ('submitted','pic_approved') THEN 1 ELSE 0 END) AS approving")
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $result = [];

        foreach (Period::cases() as $period) {
            $row = $rows->get($period->value);
            $total = (int) ($row->total ?? 0);
            $completed = (int) ($row->completed ?? 0);

            $result[] = [
                'period' => $period->value,
                'label' => $period->label(),
                'total' => $total,
                'completed' => $completed,
                // Yang sudah dikerjakan tapi masih di rantai approval. Tidak
                // dihitung sebagai "selesai" supaya laporan tidak terlihat
                // lebih baik daripada kenyataan, tapi tetap ditampilkan agar
                // jelas kenapa sebuah periode belum 100%.
                'approving' => (int) ($row->approving ?? 0),
                'percentage' => $total > 0 ? round($completed / $total * 100) : 0,
            ];
        }

        return $result;
    }

    public static function topReplacedParts(int $limit = 5): array
    {
        return PmRecordItem::where('act_replace', true)
            ->select('item_name', DB::raw('SUM(parts_replaced) as total'))
            ->groupBy('item_name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Rincian komponen yang benar-benar diganti pada periode terpilih,
     * lengkap dengan mesin tempat penggantian dilakukan.
     *
     * `topReplacedParts()` hanya menjawab "komponen apa yang sering habis",
     * sehingga angka totalnya tidak memberi tahu unit mana yang harus dicek.
     * Data ini dipakai halaman cetak PDF supaya barisnya bisa dilacak ke
     * mesin dan periodenya. Dikelola di PHP (bukan GROUP BY) karena jumlah
     * barisnya kecil dan relasi mesin sudah tersedia lewat `pmRecord.machine`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function replacedPartsDetail(int $year, ?string $period = null): array
    {
        return PmRecordItem::query()
            ->with('pmRecord.machine')
            ->where('act_replace', true)
            ->whereHas('pmRecord', function ($q) use ($year, $period) {
                $q->where('year', $year);
                if ($period) $q->where('period', $period);
            })
            ->get()
            ->map(function (PmRecordItem $item) {
                $record = $item->pmRecord;
                $machine = $record?->machine;

                return [
                    'machine_code' => $machine?->code,
                    'machine_name' => $machine?->name,
                    'machine_location' => $machine?->location,
                    'machine_area' => $machine?->sub_category,
                    'period' => $record?->period,
                    'period_label' => Period::tryFrom((string) $record?->period)?->label() ?? $record?->period,
                    'item_name' => $item->item_name,
                    'spec' => $item->spec,
                    'total' => (int) $item->parts_replaced,
                ];
            })
            ->sortBy([
                ['machine_code', 'asc'],
                ['period', 'asc'],
                ['item_name', 'asc'],
            ])
            ->values()
            ->all();
    }

    public static function actionSummary(int $year, ?string $period = null): array
    {
        $query = PmRecordItem::whereHas('pmRecord', function ($q) use ($year, $period) {
            $q->where('year', $year);
            if ($period) $q->where('period', $period);
        });

        return [
            'clean' => (clone $query)->where('act_clean', true)->count(),
            'repair' => (clone $query)->where('act_repair', true)->count(),
            'lubricate' => (clone $query)->where('act_lubricate', true)->count(),
            'replace' => (clone $query)->where('act_replace', true)->count(),
        ];
    }
}
