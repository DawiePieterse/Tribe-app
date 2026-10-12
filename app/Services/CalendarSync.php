<?php

namespace App\Services;

use App\Models\CalendarFeed;
use App\Models\Event;
use App\Support\Ics\IcsParser;
use App\Support\SafeUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Copies a subscribed calendar's events into Tribe. Each sync replaces the feed's events with what the
 * source says now (matched on the event's UID), so edits and cancellations at the source come through.
 */
class CalendarSync
{
    /** How far back and ahead a feed's events are kept, so a school's ten-year archive stays out. */
    private const KEEP_MONTHS_BACK = 3;

    private const KEEP_MONTHS_AHEAD = 24;

    private const MAX_EVENTS = 2000;

    public function __construct(private IcsParser $parser) {}

    /** @return bool whether the sync worked; the reason it didn't is kept on the feed. */
    public function sync(CalendarFeed $feed): bool
    {
        try {
            $ics = $this->fetch($feed->fetchUrl());
            if (! str_contains($ics, 'BEGIN:VCALENDAR')) {
                throw new \RuntimeException(__('Die skakel gee nie ’n kalender (.ics) terug nie.'));
            }
            $count = $this->store($feed, $this->parser->events($ics));
        } catch (\Throwable $e) {
            $feed->update(['last_error' => mb_substr($e->getMessage(), 0, 250)]);

            return false;
        }

        $feed->update(['synced_at' => now(), 'last_error' => null]);

        return $count >= 0;
    }

    private function fetch(string $url): string
    {
        if (! SafeUrl::isPublicHttp($url)) {
            throw new \RuntimeException(__('Daardie skakel kan nie oopgemaak word nie.'));
        }

        try {
            $response = Http::timeout(20)->withOptions(SafeUrl::httpOptions($url))
                ->withHeaders(['Accept' => 'text/calendar, */*'])
                ->get($url);
        } catch (\Throwable) {
            throw new \RuntimeException(__('Die kalender het nie geantwoord nie.'));
        }

        if (! $response->successful()) {
            throw new \RuntimeException(__('Die kalender het nie geantwoord nie (fout :status).', ['status' => $response->status()]));
        }

        return substr($response->body(), 0, 10_000_000);
    }

    /**
     * @param  list<array{uid: string, title: string, starts_on: string, ends_on: string|null, start_time: string|null, end_time: string|null, location: string|null, notes: string|null, repeats: string, repeat_until: string|null}>  $events
     */
    private function store(CalendarFeed $feed, array $events): int
    {
        $from = CarbonImmutable::today()->subMonths(self::KEEP_MONTHS_BACK)->toDateString();
        $to = CarbonImmutable::today()->addMonths(self::KEEP_MONTHS_AHEAD)->toDateString();

        $keep = array_filter($events, fn ($e) => $e['starts_on'] <= $to
            && ($e['repeats'] !== 'none'
                ? ($e['repeat_until'] === null || $e['repeat_until'] >= $from)
                : ($e['ends_on'] ?? $e['starts_on']) >= $from));
        $keep = array_slice(array_values($keep), 0, self::MAX_EVENTS);

        DB::transaction(function () use ($feed, $keep): void {
            $uids = [];

            foreach ($keep as $e) {
                $uids[] = $e['uid'];
                Event::query()->updateOrCreate(
                    ['feed_id' => $feed->id, 'external_uid' => $e['uid']],
                    [
                        'household_id' => $feed->household_id,
                        'kind' => Event::EVENT,
                        'title' => $e['title'],
                        'starts_on' => $e['starts_on'],
                        'ends_on' => $e['ends_on'],
                        'start_time' => $e['start_time'],
                        'end_time' => $e['end_time'],
                        'location' => $e['location'],
                        'notes' => $e['notes'],
                        'repeats' => $e['repeats'],
                        'repeat_until' => $e['repeat_until'],
                    ],
                );
            }

            Event::query()->where('feed_id', $feed->id)->whereNotIn('external_uid', $uids ?: [''])->delete();
        });

        return count($keep);
    }
}
