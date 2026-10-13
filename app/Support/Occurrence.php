<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Member;
use Carbon\CarbonImmutable;

/**
 * One line on the agenda: an event on a given day, or a birthday. Yearly events and birthdays
 * become one occurrence per year.
 */
final class Occurrence
{
    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly string $title,
        public readonly string $kind,
        public readonly string $colour,
        public readonly ?Event $event = null,
        public readonly ?Member $member = null,
        public readonly ?string $time = null,
        public readonly ?string $detail = null,
        /** "Neem: Pa · Haal: ?" for a child's activity, from today on. */
        public readonly ?string $lift = null,
    ) {}

    /** Whether someone still has to be found to take or fetch. */
    public function liftOpen(): bool
    {
        return $this->lift !== null && str_contains($this->lift, '?');
    }

    public function isBirthday(): bool
    {
        return $this->kind === 'birthday';
    }

    public function sortKey(): string
    {
        return $this->date->format('Y-m-d').' '.($this->time ?? '00:00').' '.$this->title;
    }
}
