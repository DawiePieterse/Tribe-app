<?php

namespace App\Services;

use App\Models\Lift;
use App\Models\Member;
use App\Support\Dates;
use App\Support\Occurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What one person needs to know about tomorrow: the entries they see, the weather, the lifts they
 * give, and the lifts nobody has taken. Sent as a push and/or an email by `tribe:remind`.
 *
 * @phpstan-import-type Forecast from Weather
 *
 * @phpstan-type Summary array{date: CarbonImmutable, entries: Collection<int, Occurrence>, mine: list<string>, open: list<string>, weather: Forecast|null}
 */
class EveningSummary
{
    /** @var array<int, Collection<int, Occurrence>> the next days per household: everyone in it sees the same */
    private array $coming = [];

    public function __construct(private Agenda $agenda, private Weather $weather) {}

    /** @return Summary */
    public function for(Member $member, CarbonImmutable $tomorrow): array
    {
        $coming = $this->coming[$member->household_id] ??= $this->agenda->between($member, $tomorrow, $tomorrow->addDays(2));
        $entries = $coming->filter(fn (Occurrence $o) => $o->date->isSameDay($tomorrow))->values();

        $mine = [];
        foreach ($entries->filter(fn (Occurrence $o) => $o->needsLift) as $o) {
            foreach (Lift::DIRECTIONS as $direction) {
                if ($o->driver($direction)?->id !== $member->id) {
                    continue;
                }
                $there = $direction === Lift::THERE;
                $time = $there ? $o->event?->startLabel() : $o->event?->endLabel();
                $mine[] = implode(' ', array_filter([
                    $there ? __('Jy neem') : __('Jy haal'),
                    $o->event?->member?->firstName(),
                    $time !== null ? __('om :t', ['t' => $time]) : null,
                ])).($o->event?->location ? ' · '.$o->event->location : '');
            }
        }

        $open = $coming->filter(fn (Occurrence $o) => $o->liftOpen() && $o->event?->member?->household_id === $member->household_id)
            ->map(fn (Occurrence $o) => Dates::shortDay($o->date).' '.$o->title.' ('.$o->event?->member?->firstName().'): '.$o->liftLine())->values()->all();

        return [
            'date' => $tomorrow,
            'entries' => $entries,
            'mine' => $mine,
            'open' => $open,
            'weather' => $this->weather->for($member->household, $tomorrow),
        ];
    }

    /** One line per entry: "15:00 Ballet (Anri) · Dansstudio". */
    public static function entryLine(Occurrence $o): string
    {
        return trim(($o->time ? $o->time.' ' : '').$o->title
            .($o->event?->member ? ' ('.$o->event->member->firstName().')' : '')
            .($o->event?->location ? ' · '.$o->event->location : ''));
    }

    /** "Môre, Wo 14 Oktober" */
    public static function heading(CarbonImmutable $tomorrow): string
    {
        return __('Môre, :day', ['day' => Dates::short($tomorrow, 'F')]);
    }
}
