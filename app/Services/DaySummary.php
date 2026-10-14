<?php

namespace App\Services;

use App\Models\Event;
use App\Models\GatheringItem;
use App\Models\ListItem;
use App\Models\Member;
use App\Models\TodoList;
use App\Support\Claude;
use App\Support\Dates;
use App\Support\Occurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * "Vandag in kort" on the home screen: the weather, what is on today (and what is already over), the
 * lifts, and what still has to be bought or brought. Claude writes it as a few warm sentences when
 * ANTHROPIC_API_KEY is set; otherwise (or when Claude doesn't answer) Tribe's own plain sentences.
 *
 * Claude's text is kept for the day per person and only written again when the facts change (an
 * entry passes, something is ticked off the list), so the home screen doesn't wait for it: until it
 * is there, the plain version shows and the page fetches Claude's (`home.summary`).
 *
 * @phpstan-type Facts array{lines: list<string>, plain: string, key: string}
 */
class DaySummary
{
    /** At most this many shopping items are named; the rest are counted. */
    private const ITEMS = 25;

    public function __construct(private EveningSummary $day, private Agenda $agenda) {}

    /** @return Facts */
    public function facts(Member $me, CarbonImmutable $now): array
    {
        $today = $now->startOfDay();
        $summary = $this->day->for($me, $today);
        $family = $me->can('family');

        $entries = $summary['entries']->map(fn (Occurrence $o) => EveningSummary::entryLine($o).($o->isOver($now) ? ' ('.__('verby').')' : ''))->values()->all();
        $weather = $summary['weather'];

        $items = ! $family ? [] : ListItem::query()
            ->where('done', false)
            ->whereHas('list', fn ($q) => $q->where('household_id', $me->household_id)->where('kind', TodoList::SHOPPING))
            ->orderBy('id')
            ->pluck('title')
            ->all();

        // What this household said it would bring to a gathering this week.
        $bring = ! $family ? [] : GatheringItem::query()
            ->with('event')
            ->where('household_id', $me->household_id)
            ->whereHas('event', fn ($q) => $q->where('kind', Event::GATHERING)
                ->whereBetween('starts_on', [$today->toDateString(), $today->addDays(6)->toDateString()]))
            ->get()
            ->map(fn (GatheringItem $item) => $item->title.' ('.$item->event->title.', '
                .($item->event->starts_on->isSameDay($today) ? __('vandag') : Dates::shortDay($item->event->starts_on)).')')
            ->all();

        // Tomorrow, so a birthday or an early start can be mentioned in passing.
        $tomorrow = $this->agenda->between($me, $today->addDay(), $today->addDay())
            ->take(6)->map(fn (Occurrence $o) => EveningSummary::entryLine($o))->values()->all();

        $town = $me->household->townName();
        $lines = array_values(array_filter([
            __('Dag: :day', ['day' => Dates::short($today, 'F')]),
            $weather ? __('Weer:').' '.($town ? $town.', ' : '').Weather::summary($weather) : null,
            $entries ? __('Vandag op die kalender:').' '.implode('; ', $entries) : __('Niks op die kalender vandag nie.'),
            $summary['mine'] ? __('Jou ritte vandag:').' '.implode('; ', $summary['mine']) : null,
            $summary['open'] ? __('Ritte waarvoor nog niemand gekies is nie:').' '.implode('; ', $summary['open']) : null,
            ! $family ? null : ($items
                ? __('Op die inkopielys (:n):', ['n' => count($items)]).' '.implode(', ', array_slice($items, 0, self::ITEMS)).(count($items) > self::ITEMS ? ' …' : '')
                : __('Die inkopielys is leeg.')),
            $bring ? __('Om saam te bring:').' '.implode('; ', $bring) : null,
            $tomorrow ? __('Môre:').' '.implode('; ', $tomorrow) : null,
        ]));

        return [
            'lines' => $lines,
            'plain' => $this->plain($entries, $summary['mine'], $weather ? Weather::summary($weather) : null, $items, $bring),
            // The time of day changes the tone (morning or evening), so it is part of what is kept.
            'key' => 'day-summary:'.$me->id.':'.$today->toDateString().':'.$this->partOfDay($now).':'.sha1(implode("\n", $lines)),
        ];
    }

    /**
     * Claude's summary if it has been written for these facts already.
     *
     * @param  Facts  $facts
     */
    public function cached(array $facts): ?string
    {
        $text = Cache::get($facts['key']);

        return is_string($text) ? $text : null;
    }

    /**
     * Whether Claude's summary is still to be written (the page then fetches it).
     *
     * @param  Facts  $facts
     */
    public function pending(Member $me, array $facts): bool
    {
        return Claude::available() && ! Cache::has('day-summary-failed:'.$me->id) && $this->cached($facts) === null;
    }

    /**
     * Claude's summary, written now if needed; the plain one without a key or when Claude fails
     * (then Claude isn't asked again for ten minutes).
     *
     * @param  Facts  $facts
     */
    public function text(Member $me, CarbonImmutable $now, array $facts): string
    {
        if (! Claude::available() || Cache::has('day-summary-failed:'.$me->id)) {
            return $facts['plain'];
        }

        if (($cached = $this->cached($facts)) !== null) {
            return $cached;
        }

        try {
            $input = Claude::tool(
                implode("\n", [
                    'You write the short "today" note at the top of a private family app, for one family member, in Afrikaans (South African, warm and natural, as family would say it).',
                    'Write 2 to 4 short sentences addressed to them as "jy". No greeting (the app already says hello), no headings, lists, markdown or emoji.',
                    'Use only the facts given; never invent events, people, times or items. Leave out what is already over, or mention it in passing at most.',
                    'Mention the weather in a few words and what to wear or take along if the facts suggest it, what is still ahead today and any lifts, and what still has to be bought or brought.',
                    'Tomorrow is given only so you can mention something notable in one short clause (a birthday, an early start); otherwise leave it out.',
                    'Summarise a long shopping list: name a few items and say how many more ("melk, brood en nog 6 dinge").',
                    'It is now '.$now->format('H:i').' on '.$now->locale('en')->isoFormat('dddd').'; in the evening, keep it brief.',
                ]),
                implode("\n", $facts['lines']),
                'day_summary',
                'The note for the home screen.',
                [
                    'type' => 'object',
                    'properties' => ['summary' => ['type' => 'string', 'description' => '2 to 4 sentences in Afrikaans.']],
                    'required' => ['summary'],
                ],
                600,
                25,
            );
        } catch (RuntimeException) {
            Cache::put('day-summary-failed:'.$me->id, true, now()->addMinutes(10));

            return $facts['plain'];
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($input['summary'] ?? ''))));
        if ($text === '') {
            return $facts['plain'];
        }

        $text = mb_substr($text, 0, 700);
        Cache::put($facts['key'], $text, $now->endOfDay());

        return $text;
    }

    /**
     * Tribe's own version: "Vandag: 15:00 Ballet (Anri). 14–15 °C · bewolk. Inkopies: melk, brood en nog 3."
     *
     * @param  list<string>  $entries
     * @param  list<string>  $mine
     * @param  list<string>  $items
     * @param  list<string>  $bring
     */
    private function plain(array $entries, array $mine, ?string $weather, array $items, array $bring): string
    {
        $ahead = array_values(array_filter($entries, fn (string $e) => ! str_ends_with($e, '('.__('verby').')')));

        $parts = [
            $ahead ? __('Vandag: :list.', ['list' => implode(', ', $ahead)])
                : ($entries ? __('Alles op die kalender vir vandag is verby.') : __('Niks op die kalender vandag nie.')),
            $mine ? implode('. ', $mine).'.' : null,
            $weather ? ucfirst($weather).'.' : null,
            $items ? __('Inkopies: :list.', ['list' => $this->shortList($items)]) : null,
            $bring ? __('Onthou om saam te bring: :list.', ['list' => implode(', ', $bring)]) : null,
        ];

        return implode(' ', array_filter($parts));
    }

    /** @param  list<string>  $items */
    private function shortList(array $items): string
    {
        $named = array_slice($items, 0, 3);
        $more = count($items) - count($named);

        return $more > 0
            ? __(':items en nog :n', ['items' => implode(', ', $named), 'n' => $more])
            : implode(', ', $named);
    }

    private function partOfDay(CarbonImmutable $now): string
    {
        return $now->hour < 12 ? 'oggend' : ($now->hour < 17 ? 'middag' : 'aand');
    }
}
