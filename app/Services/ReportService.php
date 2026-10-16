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

    /**
     * Komponen paling sering diganti, dibatasi tahun dan (opsional) periode
     * yang sedang dilihat.
     *
     * Sebelumnya fungsi ini menghitung seluruh tahun sekaligus sehingga
     * angkanya tidak sinkron dengan Ringkasan Tindakan / Detail Komponen
     * Diganti yang sudah difilter. Sekarang sumbernya PM record yang sama
     * supaya ringkasan dan rincian selalu cocok.
     */
    public static function topReplacedParts(int $year, ?string $period = null, int $limit = 5): array
    {
        return PmRecordItem::query()
            ->join('pm_records', 'pm_record_items.pm_record_id', '=', 'pm_records.id')
            ->where('pm_record_items.act_replace', true)
            ->where('pm_records.year', $year)
            ->when($period, fn ($q) => $q->where('pm_records.period', $period))
            ->select('pm_record_items.item_name', DB::raw('SUM(pm_record_items.parts_replaced) as total'))
            ->groupBy('pm_record_items.item_name')
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

    /**
     * Ringkasan tindakan per periode, dihitung dalam SATU query grouped
     * (bukan 4 COUNT × 6 periode) supaya Beranda tetap ringan.
     *
     * @return array<string, array{clean:int,repair:int,lubricate:int,replace:int}>
     */
    public static function actionSummaryByPeriod(int $year): array
    {
        $rows = PmRecordItem::query()
            ->join('pm_records', 'pm_record_items.pm_record_id', '=', 'pm_records.id')
            ->where('pm_records.year', $year)
            ->groupBy('pm_records.period')
            ->selectRaw('pm_records.period')
            ->selectRaw('SUM(CASE WHEN pm_record_items.act_clean = 1 THEN 1 ELSE 0 END) AS clean')
            ->selectRaw('SUM(CASE WHEN pm_record_items.act_repair = 1 THEN 1 ELSE 0 END) AS repair')
            ->selectRaw('SUM(CASE WHEN pm_record_items.act_lubricate = 1 THEN 1 ELSE 0 END) AS lubricate')
            // `replace` adalah kata kunci MySQL, jadi hasil agregatnya diberi
            // alias lain (`replace_count`) lalu dipetakan ke kunci `replace`.
            ->selectRaw('SUM(CASE WHEN pm_record_items.act_replace = 1 THEN 1 ELSE 0 END) AS replace_count')
            ->get()
            ->keyBy('period');

        $result = [];

        foreach (Period::cases() as $period) {
            $row = $rows->get($period->value);

            $result[$period->value] = [
                'clean' => (int) ($row->clean ?? 0),
                'repair' => (int) ($row->repair ?? 0),
                'lubricate' => (int) ($row->lubricate ?? 0),
                'replace' => (int) ($row->replace_count ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Komponen paling sering diganti untuk SETIAP periode dalam satu query
     * grouped; tiap periode diambil `$limit` teratas. Dipakai kartu Beranda
     * supaya angkanya mengikuti filter periode yang sedang dipilih.
     *
     * @return array<string, array<int, array{item_name:string,total:int}>>
     */
    public static function topReplacedPartsByPeriod(int $year, int $limit = 5): array
    {
        $rows = PmRecordItem::query()
            ->join('pm_records', 'pm_record_items.pm_record_id', '=', 'pm_records.id')
            ->where('pm_record_items.act_replace', true)
            ->where('pm_records.year', $year)
            ->groupBy('pm_records.period', 'pm_record_items.item_name')
            ->selectRaw('pm_records.period, pm_record_items.item_name, SUM(pm_record_items.parts_replaced) as total')
            ->orderByDesc('total')
            ->get();

        $result = [];

        foreach (Period::cases() as $period) {
            $result[$period->value] = $rows
                ->where('period', $period->value)
                ->take($limit)
                ->map(fn ($row) => [
                    'item_name' => $row->item_name,
                    'total' => (int) $row->total,
                ])
                ->values()
                ->all();
        }

        return $result;
    }

    /**
     * Data kartu laporan Beranda: `actions` + `topParts` untuk tiap periode
     * ditambah kunci `all` sebagai agregat setahun penuh. Dipanggil sekali per
     * request supaya filter periode di Beranda bisa berpindah tanpa memanggil
     * server lagi.
     *
     * @return array<string, array{actions:array,topParts:array}>
     */
    public static function summaryByPeriod(int $year): array
    {
        $actionsByPeriod = self::actionSummaryByPeriod($year);
        $topPartsByPeriod = self::topReplacedPartsByPeriod($year);

        $allActions = ['clean' => 0, 'repair' => 0, 'lubricate' => 0, 'replace' => 0];

        foreach ($actionsByPeriod as $actions) {
            foreach ($allActions as $key => $value) {
                $allActions[$key] = $value + $actions[$key];
            }
        }

        $summary = [
            'all' => [
                'actions' => $allActions,
                'topParts' => self::topReplacedParts($year),
            ],
        ];

        foreach (Period::cases() as $period) {
            $summary[$period->value] = [
                'actions' => $actionsByPeriod[$period->value],
                'topParts' => $topPartsByPeriod[$period->value],
            ];
        }

        return $summary;
    }
}
