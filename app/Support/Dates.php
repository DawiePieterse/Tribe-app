<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Carbon's short Afrikaans day names ("Sat", "Vry") aren't what people write; these are. */
final class Dates
{
    /** The family's time zone: how times are shown, and what a time without a zone means. */
    public const ZONE = 'Africa/Johannesburg';

    public static function shortDay(CarbonImmutable $date): string
    {
        return __(['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Sa', 'So'][$date->dayOfWeekIso - 1]);
    }
}
