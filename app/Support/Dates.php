<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Carbon's short Afrikaans day names ("Sat", "Vry") aren't what people write; these are. */
final class Dates
{
    /** The family's time zone: how times are shown, and what a time without a zone means. */
    public const ZONE = 'Africa/Johannesburg';

    public static function shortDay(CarbonImmutable $date): string
    {
        return __(['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Sa', 'So'][$date->dayOfWeekIso - 1]);
    }

    /** "Wo 14 Okt" (or "Wo 14 Oktober" with $month = 'F'). */
    public static function short(CarbonImmutable $date, string $month = 'M'): string
    {
        return self::shortDay($date).' '.$date->translatedFormat('j '.$month);
    }

    /** The family's weeks run Sunday to Saturday (calendar week and month, weekly menu). */
    public const FIRST_DAY = CarbonInterface::SUNDAY;

    /** The first day (Sunday) of the week a date falls in. */
    public static function weekStart(CarbonImmutable $date): CarbonImmutable
    {
        return $date->startOfWeek(self::FIRST_DAY);
    }

    /** The first day of the week a Y-m-d date falls in; this week when the value isn't a date. */
    public static function weekStartOf(string $value): CarbonImmutable
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? CarbonImmutable::parse($value) : CarbonImmutable::today();

        return self::weekStart($date);
    }

    /**
     * Short day names in week order, Sunday first: So, Ma, Di, …
     *
     * @return list<string>
     */
    public static function weekdayNames(): array
    {
        $first = CarbonImmutable::today()->startOfWeek(self::FIRST_DAY);

        return array_map(fn (int $i) => self::shortDay($first->addDays($i)), range(0, 6));
    }

    /** How many days a month's first day sits after the start of its week (empty cells before day 1). */
    public static function offsetInWeek(CarbonImmutable $date): int
    {
        return ($date->dayOfWeek - self::FIRST_DAY + 7) % 7;
    }

    /** "11 Okt tot 17 Okt" */
    public static function weekRange(CarbonImmutable $start): string
    {
        return __(':from tot :to', ['from' => $start->translatedFormat('j M'), 'to' => $start->addDays(6)->translatedFormat('j M')]);
    }
}
