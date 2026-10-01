<?php

namespace App\Enums;

enum Period: string
{
    case JanFeb = 'jan-feb';
    case MarApr = 'mar-apr';
    case MeiJun = 'mei-jun';
    case JulAgs = 'jul-ags';
    case SepOkt = 'sep-okt';
    case NovDes = 'nov-des';

    public function label(): string
    {
        return match ($this) {
            self::JanFeb => 'Jan–Feb',
            self::MarApr => 'Mar–Apr',
            self::MeiJun => 'Mei–Jun',
            self::JulAgs => 'Jul–Ags',
            self::SepOkt => 'Sep–Okt',
            self::NovDes => 'Nov–Des',
        };
    }

    public function startMonth(): int
    {
        return match ($this) {
            self::JanFeb => 1,
            self::MarApr => 3,
            self::MeiJun => 5,
            self::JulAgs => 7,
            self::SepOkt => 9,
            self::NovDes => 11,
        };
    }

    public function endMonth(): int
    {
        return match ($this) {
            self::JanFeb => 2,
            self::MarApr => 4,
            self::MeiJun => 6,
            self::JulAgs => 8,
            self::SepOkt => 10,
            self::NovDes => 12,
        };
    }

    public static function current(): self
    {
        $month = (int) now()->format('n');
        return match (true) {
            $month <= 2 => self::JanFeb,
            $month <= 4 => self::MarApr,
            $month <= 6 => self::MeiJun,
            $month <= 8 => self::JulAgs,
            $month <= 10 => self::SepOkt,
            default => self::NovDes,
        };
    }

    public static function fromMonth(int $month): self
    {
        return match (true) {
            $month <= 2 => self::JanFeb,
            $month <= 4 => self::MarApr,
            $month <= 6 => self::MeiJun,
            $month <= 8 => self::JulAgs,
            $month <= 10 => self::SepOkt,
            default => self::NovDes,
        };
    }

    public function isFuture(int $year): bool
    {
        $currentYear = (int) now()->year;
        $currentPeriod = self::current();
        
        if ($year > $currentYear) return true;
        if ($year < $currentYear) return false;
        
        $currentMonth = (int) now()->format('n');
        $currentPeriodStart = $currentPeriod->startMonth();
        
        return $this->startMonth() > $currentPeriodStart;
    }
}
