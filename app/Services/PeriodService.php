<?php

namespace App\Services;

use App\Enums\Period;

class PeriodService
{
    public static function currentPeriod(): Period
    {
        return Period::current();
    }

    public static function currentYear(): int
    {
        return (int) now()->year;
    }

    public static function isFuturePeriod(Period $period, int $year): bool
    {
        return $period->isFuture($year);
    }

    public static function getPeriods(): array
    {
        return collect(Period::cases())->map(fn ($p) => [
            'value' => $p->value,
            'label' => $p->label(),
        ])->toArray();
    }
}
