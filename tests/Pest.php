<?php

use App\Models\Household;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Unit');

function household(string $name = 'Huis', string $colour = '#2E7D5B'): Household
{
    return Household::query()->create(['name' => $name, 'colour' => $colour]);
}

/** @param  array<string, mixed>  $attributes */
function adult(Household $household, array $attributes = []): Member
{
    static $n = 0;
    $n++;

    return Member::query()->create($attributes + [
        'household_id' => $household->id,
        'name' => "Grootmens {$n}",
        'kind' => Member::ADULT,
        'email' => "adult{$n}@example.com",
    ]);
}

/** @param  array<string, mixed>  $attributes */
function child(Household $household, array $attributes = []): Member
{
    return Member::query()->create($attributes + [
        'household_id' => $household->id,
        'name' => 'Kind',
        'kind' => Member::CHILD,
    ]);
}
