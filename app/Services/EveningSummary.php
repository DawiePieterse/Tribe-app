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
 */
class EveningSummary
{
    public function __construct(private Agenda $agenda, private Weather $weather) {}

    /**
     * @return array{date: CarbonImmutable, entries: Collection<int, Occurrence>, mine: list<string>, open: list<string>, weather: array{min: int, max: int, rain: int, wind: int, condition: string, flags: list<string>}|null}
     */
    public function for(Member $member, CarbonImmutable $tomorrow): array
    {
        $coming = $this->agenda->between($member, $tomorrow, $tomorrow->addDays(2));
        $entries = $coming->filter(fn (Occurrence $o) => $o->date->isSameDay($tomorrow))->values();

        $mine = [];
        foreach ($entries as $o) {
            if ($o->event === null || ! $o->event->isForChild()) {
                continue;
            }
            $exceptions = $o->event->lifts()->where('date', $tomorrow->toDateString())->get()->keyBy('direction');
            foreach (Lift::DIRECTIONS as $direction) {
                $driver = $exceptions->has($direction) ? $exceptions->get($direction)?->member : $o->event->usualDriver($direction);
                if ($driver?->id === $member->id) {
                    $mine[] = __(':leg :child :time:where', [
                        'leg' => $direction === Lift::THERE ? __('Jy neem') : __('Jy haal'),
                        'child' => $o->event->member?->firstName(),
                        'time' => $direction === Lift::THERE ? ($o->event->start_time ? __('om :t', ['t' => substr($o->event->start_time, 0, 5)]) : '') : ($o->event->end_time ? __('om :t', ['t' => substr($o->event->end_time, 0, 5)]) : ''),
                        'where' => $o->event->location ? ' · '.$o->event->location : '',
                    ]);
                }
            }
        }

        $open = $coming->filter(fn (Occurrence $o) => $o->liftOpen() && $o->event?->member?->household_id === $member->household_id)
            ->map(fn (Occurrence $o) => Dates::shortDay($o->date).' '.$o->title.' ('.$o->event?->member?->firstName().'): '.$o->lift)->values()->all();

        return [
            'date' => $tomorrow,
            'entries' => $entries,
            'mine' => array_map(fn ($l) => trim((string) preg_replace('/\s+/', ' ', $l)), $mine),
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
}
