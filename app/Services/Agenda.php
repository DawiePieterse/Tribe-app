<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Lift;
use App\Models\Member;
use App\Support\Occurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything on the calendar between two dates, as one list, for one member: their household's
 * private events, the whole family's events, yearly anniversaries and everyone's birthdays.
 */
class Agenda
{
    /**
     * @param  array{household?: int|null, member?: int|null}  $filter
     * @return Collection<int, Occurrence>
     */
    public function between(Member $viewer, CarbonImmutable $from, CarbonImmutable $to, array $filter = []): Collection
    {
        $from = $from->startOfDay();
        $to = $to->startOfDay();

        $events = Event::query()
            ->visibleTo($viewer)
            ->with(['member.household', 'household', 'hostHousehold', 'feed'])
            ->between($from->toDateString(), $to->toDateString())
            ->get();

        $occurrences = collect();
        $lifts = $this->lifts($events, $from, $to);

        foreach ($events as $event) {
            if (! $this->matches($event, $filter)) {
                continue;
            }

            if ($event->repeats()) {
                foreach ($this->repeatDates($event, $from, $to) as $date) {
                    $years = (int) $event->starts_on->diffInYears($date);
                    $occurrences->push(new Occurrence(
                        date: $date,
                        title: $event->title,
                        kind: $event->kind,
                        colour: $event->colour(),
                        event: $event,
                        time: $event->timeLabel(),
                        detail: $event->kind === Event::ANNIVERSARY && $years > 0
                            ? trans_choice(':count jaar|:count jaar', $years)
                            : $event->location,
                        lift: $this->liftLine($event, $date, $lifts),
                    ));
                }

                continue;
            }

            // A multi-day entry (a holiday, a weekend away) shows once, on its first day in range.
            $occurrences->push(new Occurrence(
                date: $event->starts_on->lessThan($from) ? $from : $event->starts_on,
                title: $event->title,
                kind: $event->kind,
                colour: $event->colour(),
                event: $event,
                time: $event->timeLabel(),
                detail: $event->ends_on && ! $event->ends_on->equalTo($event->starts_on)
                    ? __('tot :date', ['date' => $event->ends_on->translatedFormat('j F')])
                    : null,
                lift: $this->liftLine($event, $event->starts_on, $lifts),
            ));
        }

        $members = Member::query()->with('household')->whereNotNull('birthday')->get();

        foreach ($members as $member) {
            if (! $this->matchesMember($member, $filter) || $member->birthday === null) {
                continue;
            }

            foreach ($this->yearlyDates($member->birthday, $from, $to) as $date) {
                $age = $member->ageOn($date);
                $occurrences->push(new Occurrence(
                    date: $date,
                    title: __(':name verjaar', ['name' => $member->name]),
                    kind: 'birthday',
                    colour: $member->displayColour(),
                    member: $member,
                    detail: $age !== null && $age > 0 ? __('word :age', ['age' => $age]) : null,
                ));
            }
        }

        return $occurrences->sortBy(fn (Occurrence $o) => $o->sortKey())->values();
    }

    /**
     * Who takes and fetches, for the children's activities in range, keyed by event, date and direction.
     *
     * @param  Collection<int, Event>  $events
     * @return Collection<string, Lift>
     */
    private function lifts(Collection $events, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $ids = $events->filter(fn (Event $e) => $e->isForChild())->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        return Lift::query()->with('member')->whereIn('event_id', $ids)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (Lift $l) => Lift::key($l->event_id, $l->date->toDateString(), $l->direction));
    }

    /** @param  Collection<string, Lift>  $lifts */
    private function liftLine(Event $event, CarbonImmutable $date, Collection $lifts): ?string
    {
        if (! $event->isForChild() || $date->lessThan(CarbonImmutable::today())) {
            return null;
        }

        $who = fn (string $direction) => $lifts->get(Lift::key($event->id, $date->toDateString(), $direction))?->member?->firstName() ?? '?';

        return __('Neem: :there · Haal: :back', ['there' => $who(Lift::THERE), 'back' => $who(Lift::BACK)]);
    }

    /**
     * The days in [$from, $to] on which a repeating event happens, up to its repeat_until date.
     *
     * @return list<CarbonImmutable>
     */
    public function repeatDates(Event $event, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $origin = $event->starts_on->startOfDay();

        if ($event->repeat_until !== null && $event->repeat_until->lessThan($to)) {
            $to = $event->repeat_until->startOfDay();
        }

        if ($event->repeats === Event::YEARLY) {
            return $this->yearlyDates($origin, $from, $to);
        }

        $dates = [];

        if ($event->repeats === Event::MONTHLY) {
            // Months are counted from the start, so 31 January falls on 28 February, then 31 March.
            $n = max(0, ($from->year - $origin->year) * 12 + $from->month - $origin->month - 1);
            for (; ; $n++) {
                $date = $origin->addMonthsNoOverflow($n);
                if ($date->greaterThan($to)) {
                    break;
                }
                if ($date->greaterThanOrEqualTo($from)) {
                    $dates[] = $date;
                }
            }

            return $dates;
        }

        $step = $event->repeats === Event::FORTNIGHTLY ? 14 : 7;
        // Jump straight to the first occurrence on or after $from.
        $skip = $from->greaterThan($origin) ? (int) ceil($origin->diffInDays($from) / $step) : 0;

        for ($date = $origin->addDays($skip * $step); $date->lessThanOrEqualTo($to); $date = $date->addDays($step)) {
            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * The days in [$from, $to] on which something that started on $origin comes round again.
     * A 29 February birthday falls on 28 February in other years.
     *
     * @return list<CarbonImmutable>
     */
    public function yearlyDates(CarbonImmutable $origin, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [];

        for ($year = $from->year; $year <= $to->year; $year++) {
            $day = min($origin->day, CarbonImmutable::create($year, $origin->month, 1)->daysInMonth);
            $date = CarbonImmutable::create($year, $origin->month, $day)->startOfDay();

            if ($date->betweenIncluded($from, $to) && $date->greaterThanOrEqualTo($origin->startOfDay())) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    /** @param  array{household?: int|null, member?: int|null}  $filter */
    private function matches(Event $event, array $filter): bool
    {
        if (! empty($filter['member'])) {
            return $event->member_id === (int) $filter['member'];
        }

        if (! empty($filter['household'])) {
            $id = (int) $filter['household'];

            return $event->household_id === $id
                || $event->host_household_id === $id
                || $event->member?->household_id === $id;
        }

        return true;
    }

    /** @param  array{household?: int|null, member?: int|null}  $filter */
    private function matchesMember(Member $member, array $filter): bool
    {
        if (! empty($filter['member'])) {
            return $member->id === (int) $filter['member'];
        }

        if (! empty($filter['household'])) {
            return $member->household_id === (int) $filter['household'];
        }

        return true;
    }
}
