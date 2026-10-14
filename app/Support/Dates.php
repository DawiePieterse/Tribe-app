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

    /** The Monday of the week a Y-m-d date falls in; this week when the value isn't a date. */
    public static function mondayOf(string $value): CarbonImmutable
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? CarbonImmutable::parse($value) : CarbonImmutable::today();

        return $date->startOfWeek(CarbonInterface::MONDAY);
    }

    /** "12 Okt tot 18 Okt" */
    public static function weekRange(CarbonImmutable $monday): string
    {
        return __(':from tot :to', ['from' => $monday->translatedFormat('j M'), 'to' => $monday->addDays(6)->translatedFormat('j M')]);
    }
}
