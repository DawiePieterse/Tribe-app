<?php

namespace App\Services\Messages;

use App\Models\Household;
use App\Support\Claude;
use Carbon\CarbonImmutable;

/**
 * Asks Claude to read the plan out of a chat message. Only the message someone chose to share is sent,
 * with today's date and the household names (so "by Ouma-hulle" can be understood).
 */
final class ClaudeReader
{
    public function read(string $text, CarbonImmutable $today): Suggestion
    {
        $households = Household::query()->orderBy('id')->pluck('name')->implode(', ');

        $system = implode("\n", [
            'You read one message from a South African family\'s group chat (usually Afrikaans, sometimes English)',
            'and pull out the single plan it describes, for their shared calendar.',
            'Today is '.$today->locale('en')->isoFormat('dddd D MMMM YYYY').' (Africa/Johannesburg).',
            'The family\'s households: '.$households.'.',
            'Resolve relative dates ("Saterdag", "more", "volgende week") against today; a weekday means the next one after today.',
            'Times people mention like "3uur" or "half een" are afternoon family times (15:00, 12:30) unless the message says otherwise.',
            'Write the title short, in the message\'s language, like a calendar entry ("Sondagete by Ouma-hulle").',
            'Use kind "gathering" for a family get-together (a meal, braai, visit, party), otherwise "event".',
            'Leave a field null when the message does not say it. Never invent a date.',
        ]);

        $input = Claude::tool($system, mb_substr($text, 0, 4000), 'calendar_entry', 'The plan from the message, for the family calendar.', [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Short calendar title'],
                'kind' => ['type' => 'string', 'enum' => ['event', 'gathering']],
                'starts_on' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'],
                'ends_on' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD, only if it lasts more than one day'],
                'start_time' => ['type' => ['string', 'null'], 'description' => 'HH:MM, 24-hour'],
                'end_time' => ['type' => ['string', 'null'], 'description' => 'HH:MM, 24-hour'],
                'location' => ['type' => ['string', 'null']],
                'notes' => ['type' => ['string', 'null'], 'description' => 'Anything else worth keeping, such as what to bring'],
            ],
            'required' => ['title', 'kind', 'starts_on'],
        ], 500, 20);

        return Suggestion::fromArray($input + ['source' => 'ai']);
    }
}
