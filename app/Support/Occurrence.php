<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Lift;
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
        /** A child's activity, from today on: someone takes them there and fetches them. */
        public readonly bool $needsLift = false,
        public readonly ?Member $there = null,
        public readonly ?Member $back = null,
    ) {}

    /** Whether someone still has to be found to take or fetch. */
    public function liftOpen(): bool
    {
        return $this->needsLift && ($this->there === null || $this->back === null);
    }

    /** "Neem: Pa · Haal: ?" */
    public function liftLine(): ?string
    {
        return ! $this->needsLift ? null : __('Neem: :there · Haal: :back', [
            'there' => $this->there?->firstName() ?? '?',
            'back' => $this->back?->firstName() ?? '?',
        ]);
    }

    /** Who does this leg. */
    public function driver(string $direction): ?Member
    {
        return $direction === Lift::THERE ? $this->there : $this->back;
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
