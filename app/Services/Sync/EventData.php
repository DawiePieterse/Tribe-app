<?php

namespace App\Services\Sync;

use App\Models\Event;
use App\Support\Dates;
use Carbon\CarbonImmutable;

/**
 * The parts of an event that are kept the same on both sides of a two-way sync, in one normal form
 * (dates Y-m-d, times H:i, local time in Africa/Johannesburg), so the two sides can be compared.
 */
final readonly class EventData
{
    public function __construct(
        public string $title,
        public string $startsOn,
        public ?string $endsOn = null,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?string $location = null,
        public ?string $notes = null,
        public string $repeats = Event::ONCE,
        public ?string $repeatUntil = null,
    ) {}

    public static function fromEvent(Event $event): self
    {
        return self::make(
            $event->title,
            $event->starts_on->toDateString(),
            $event->ends_on?->toDateString(),
            $event->start_time,
            $event->end_time,
            $event->location,
            $event->notes,
            $event->repeats,
            $event->repeat_until?->toDateString(),
        );
    }

    /** Trims, shortens and tidies values from either side so equal events compare equal. */
    public static function make(
        string $title, string $startsOn, ?string $endsOn = null, ?string $startTime = null, ?string $endTime = null,
        ?string $location = null, ?string $notes = null, string $repeats = Event::ONCE, ?string $repeatUntil = null,
    ): self {
        $startTime = $startTime !== null && $startTime !== '' ? substr($startTime, 0, 5) : null;
        $endTime = $startTime !== null && $endTime !== null && $endTime !== '' ? substr($endTime, 0, 5) : null;
        $endsOn = $endsOn !== null && $endsOn > $startsOn ? $endsOn : null;
        $repeats = in_array($repeats, Event::REPEATS, true) ? $repeats : Event::ONCE;

        return new self(
            mb_substr(trim($title), 0, 255) ?: '(sonder naam)',
            $startsOn,
            $endsOn,
            $startTime,
            $endTime,
            mb_substr(trim((string) $location), 0, 255) ?: null,
            mb_substr(trim(str_replace("\r\n", "\n", (string) $notes)), 0, 5000) ?: null,
            $repeats,
            $repeats === Event::ONCE ? null : $repeatUntil,
        );
    }

    /**
     * From a start and end moment as another calendar gives them. An all-day end is the day after
     * the last day, as in iCalendar, Google and Outlook.
     */
    public static function fromMoments(
        string $title, CarbonImmutable $start, ?CarbonImmutable $end, bool $allDay,
        ?string $location, ?string $notes, string $repeats = Event::ONCE, ?string $repeatUntil = null,
    ): self {
        $start = $allDay ? $start : $start->setTimezone(Dates::ZONE);
        $end = $end === null ? null : ($allDay ? $end->subDay() : $end->setTimezone(Dates::ZONE));
        $sameDay = $end !== null && $end->toDateString() === $start->toDateString();

        return self::make(
            $title,
            $start->toDateString(),
            $end?->toDateString(),
            $allDay ? null : $start->format('H:i'),
            $allDay || ! $sameDay ? null : $end->format('H:i'),
            $location,
            $notes,
            $repeats,
            $repeatUntil,
        );
    }

    /** @return array<string, string|null> the Event attributes */
    public function attributes(): array
    {
        return [
            'title' => $this->title,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'location' => $this->location,
            'notes' => $this->notes,
            'repeats' => $this->repeats,
            'repeat_until' => $this->repeatUntil,
        ];
    }

    public function hash(): string
    {
        return hash('sha256', (string) json_encode($this->attributes()));
    }

    public function isAllDay(): bool
    {
        return $this->startTime === null;
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->startsOn.' '.($this->startTime ?? '00:00'), Dates::ZONE);
    }

    /** All-day: the day after the last day. Timed without an end: an hour. */
    public function end(): CarbonImmutable
    {
        if ($this->isAllDay()) {
            return CarbonImmutable::parse($this->endsOn ?? $this->startsOn, Dates::ZONE)->addDay();
        }

        $start = $this->start();
        if ($this->endTime === null) {
            return $start->addHour();
        }

        $end = CarbonImmutable::parse(($this->endsOn ?? $this->startsOn).' '.$this->endTime, Dates::ZONE);

        return $end->greaterThan($start) ? $end : $start->addHour();
    }

    /** The iCalendar RRULE (without "RRULE:"), or null when it happens once. */
    public function rrule(): ?string
    {
        $rule = match ($this->repeats) {
            Event::WEEKLY => 'FREQ=WEEKLY',
            Event::FORTNIGHTLY => 'FREQ=WEEKLY;INTERVAL=2',
            Event::MONTHLY => 'FREQ=MONTHLY',
            Event::YEARLY => 'FREQ=YEARLY',
            default => null,
        };

        if ($rule !== null && $this->repeatUntil !== null) {
            $until = CarbonImmutable::parse($this->repeatUntil, Dates::ZONE);
            // UNTIL has the same kind of value as the start: a date for all-day, else a UTC moment.
            $rule .= ';UNTIL='.($this->isAllDay() ? $until->format('Ymd') : $until->endOfDay()->utc()->format('Ymd\THis\Z'));
        }

        return $rule;
    }

    /** Whether it falls (or, repeating, still happens) between the two dates. */
    public function overlaps(string $from, string $to): bool
    {
        if ($this->startsOn > $to) {
            return false;
        }

        return $this->repeats !== Event::ONCE
            ? $this->repeatUntil === null || $this->repeatUntil >= $from
            : ($this->endsOn ?? $this->startsOn) >= $from;
    }
}
