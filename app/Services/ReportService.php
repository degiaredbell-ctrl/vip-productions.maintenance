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
        $result = [];
        foreach (Period::cases() as $period) {
            $total = PmRecord::where('year', $year)->where('period', $period->value)->count();
            $completed = PmRecord::where('year', $year)->where('period', $period->value)
                ->where('status', 'approved')->count();
            $result[] = [
                'period' => $period->value,
                'label' => $period->label(),
                'total' => $total,
                'completed' => $completed,
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
