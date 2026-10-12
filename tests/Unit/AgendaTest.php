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
    Event::query()->create(['kind' => Event::ANNIVERSARY, 'title' => 'Troudag', 'starts_on' => '1984-06-02', 'repeats' => Event::YEARLY]);

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

it('repeats weekly from the first day, up to the end date', function () {
    $me = adult(household());
    Event::query()->create(['title' => 'Swem', 'starts_on' => '2026-09-01', 'repeats' => Event::WEEKLY, 'repeat_until' => '2026-10-20']);

    $dates = app(Agenda::class)->between($me, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'))
        ->map(fn ($o) => $o->date->toDateString())->all();

    expect($dates)->toBe(['2026-10-06', '2026-10-13', '2026-10-20']);
});

it('repeats every two weeks', function () {
    $me = adult(household());
    Event::query()->create(['title' => 'Kerkkoor', 'starts_on' => '2026-10-04', 'repeats' => Event::FORTNIGHTLY]);

    $dates = app(Agenda::class)->between($me, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-11-30'))
        ->map(fn ($o) => $o->date->toDateString())->all();

    expect($dates)->toBe(['2026-10-18', '2026-11-01', '2026-11-15', '2026-11-29']);
});

it('repeats monthly without spilling into the next month', function () {
    $me = adult(household());
    Event::query()->create(['title' => 'Huur', 'starts_on' => '2026-01-31', 'repeats' => Event::MONTHLY]);

    $dates = app(Agenda::class)->between($me, CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-04-30'))
        ->map(fn ($o) => $o->date->toDateString())->all();

    expect($dates)->toBe(['2026-02-28', '2026-03-31', '2026-04-30']);
});

it('does not show a weekly event before it starts', function () {
    $me = adult(household());
    Event::query()->create(['title' => 'Nuut', 'starts_on' => '2026-10-20', 'repeats' => Event::WEEKLY]);

    $items = app(Agenda::class)->between($me, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-25'));
    expect($items->pluck('date')->map->toDateString()->all())->toBe(['2026-10-20']);
});
