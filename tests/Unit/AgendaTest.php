<?php

use App\Models\Event;
use App\Services\Agenda;
use Carbon\CarbonImmutable;

it('shows birthdays every year, with the age turned', function () {
    $house = household();
    $me = adult($house, ['birthday' => '1990-03-15']);
    $kid = child($house, ['name' => 'Anri', 'birthday' => '2014-03-20']);

    $items = app(Agenda::class)->between($me, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'));

    $birthday = $items->first(fn ($o) => $o->member?->is($kid));
    expect($birthday->date->toDateString())->toBe('2026-03-20')
        ->and($birthday->detail)->toBe('word 12');
});

it('puts a 29 February birthday on 28 February in other years', function () {
    $me = adult(household(), ['birthday' => '2000-02-29']);

    $dates = app(Agenda::class)->yearlyDates($me->birthday, CarbonImmutable::parse('2027-01-01'), CarbonImmutable::parse('2027-12-31'));
    expect($dates[0]->toDateString())->toBe('2027-02-28');
});

it('repeats anniversaries yearly and counts the years', function () {
    $me = adult(household());
    Event::query()->create(['kind' => Event::ANNIVERSARY, 'title' => 'Troudag', 'starts_on' => '1984-06-02', 'yearly' => true]);

    $items = app(Agenda::class)->between($me, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));
    expect($items->first()->detail)->toBe('42 jaar');
});

it('shows a holiday that started last month on the first day of this one', function () {
    $me = adult(household());
    Event::query()->create(['kind' => Event::SCHOOL, 'title' => 'Vakansie', 'starts_on' => '2026-09-25', 'ends_on' => '2026-10-05']);

    $items = app(Agenda::class)->between($me, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));
    expect($items->first()->date->toDateString())->toBe('2026-10-01');
});

it('filters to one household', function () {
    $ours = household('Ons');
    $theirs = household('Hulle');
    $me = adult($ours, ['birthday' => '1980-05-05']);
    adult($theirs, ['birthday' => '1981-05-06']);

    $items = app(Agenda::class)->between($me, CarbonImmutable::parse('2026-05-01'), CarbonImmutable::parse('2026-05-31'), ['household' => $ours->id]);
    expect($items)->toHaveCount(1);
});
