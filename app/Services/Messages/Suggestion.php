<?php

namespace App\Services\Messages;

/**
 * What Tribe read out of a chat message: the makings of a calendar entry, to be checked by a person
 * before it is saved. Any part can be missing.
 */
final class Suggestion
{
    public function __construct(
        public readonly string $title,
        public readonly string $kind = 'event',
        public readonly ?string $startsOn = null,
        public readonly ?string $endsOn = null,
        public readonly ?string $startTime = null,
        public readonly ?string $endTime = null,
        public readonly ?string $location = null,
        public readonly ?string $notes = null,
        public readonly string $source = 'simple',
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
        $time = fn ($v) => is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;
        $text = fn ($v, int $max) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;

        return new self(
            title: $text($data['title'] ?? null, 255) ?? __('Nuwe plan'),
            kind: ($data['kind'] ?? null) === 'gathering' ? 'gathering' : 'event',
            startsOn: $date($data['starts_on'] ?? null),
            endsOn: $date($data['ends_on'] ?? null),
            startTime: $time($data['start_time'] ?? null),
            endTime: $time($data['end_time'] ?? null),
            location: $text($data['location'] ?? null, 255),
            notes: $text($data['notes'] ?? null, 2000),
            source: ($data['source'] ?? null) === 'ai' ? 'ai' : 'simple',
        );
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'title' => $this->title, 'kind' => $this->kind, 'starts_on' => $this->startsOn, 'ends_on' => $this->endsOn,
            'start_time' => $this->startTime, 'end_time' => $this->endTime, 'location' => $this->location,
            'notes' => $this->notes, 'source' => $this->source,
        ];
    }

    /**
     * Query parameters for the new-event form (the same Afrikaans names the form already reads).
     *
     * @return array<string, string>
     */
    public function formQuery(?string $message = null): array
    {
        return array_filter([
            'soort' => $this->kind,
            'titel' => $this->title,
            'datum' => $this->startsOn,
            'tot' => $this->endsOn,
            'tyd' => $this->startTime,
            'eindtyd' => $this->endTime,
            'waar' => $this->location,
            'notas' => $this->notes ?? $message,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
