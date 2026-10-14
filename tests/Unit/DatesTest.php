<?php

use App\Support\Dates;
use Carbon\CarbonImmutable;

it('starts the family’s weeks on Sunday', function () {
    // Wednesday 14 October 2026 is in the week of Sunday 11 October.
    expect(Dates::weekStartOf('2026-10-14')->toDateString())->toBe('2026-10-11')
        ->and(Dates::weekStartOf('2026-10-11')->toDateString())->toBe('2026-10-11')
        ->and(Dates::weekStartOf('2026-10-17')->toDateString())->toBe('2026-10-11')
        ->and(Dates::weekdayNames())->toBe(['So', 'Ma', 'Di', 'Wo', 'Do', 'Vr', 'Sa']);
});

it('puts the first of the month under its weekday in a Sunday-first grid', function () {
    expect(Dates::offsetInWeek(CarbonImmutable::parse('2026-11-01')))->toBe(0) // a Sunday
        ->and(Dates::offsetInWeek(CarbonImmutable::parse('2026-10-01')))->toBe(4) // a Thursday
        ->and(Dates::offsetInWeek(CarbonImmutable::parse('2026-08-01')))->toBe(6); // a Saturday
});
