<?php

namespace App\Services;

use App\Models\Event;
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
            ->with(['member.household', 'household', 'hostHousehold'])
            ->where(function ($q) use ($from, $to): void {
                $q->where('yearly', true)
                    ->orWhere(function ($q) use ($from, $to): void {
                        $q->where('starts_on', '<=', $to->toDateString())
                            ->whereRaw('COALESCE(ends_on, starts_on) >= ?', [$from->toDateString()]);
                    });
            })
            ->get();

        $occurrences = collect();

        foreach ($events as $event) {
            if (! $this->matches($event, $filter)) {
                continue;
            }

            if ($event->yearly) {
                foreach ($this->yearlyDates($event->starts_on, $from, $to) as $date) {
                    $years = (int) $event->starts_on->diffInYears($date);
                    $occurrences->push(new Occurrence(
                        date: $date,
                        title: $event->title,
                        kind: $event->kind,
                        colour: $event->colour(),
                        event: $event,
                        time: $event->timeLabel(),
                        detail: $years > 0 ? trans_choice(':count jaar|:count jaar', $years) : null,
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
