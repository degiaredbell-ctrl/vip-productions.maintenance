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
