<?php

namespace App\Services\Sync;

use App\Models\CalendarConnection;
use App\Models\Event;
use App\Support\Dates;
use App\Support\Ics\IcsParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/** Google Calendar API v3. */
class GoogleCalendar extends OAuthProvider
{
    private const API = 'https://www.googleapis.com/calendar/v3';

    public function __construct(private IcsParser $parser) {}

    protected function key(): string
    {
        return CalendarConnection::GOOGLE;
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    protected function scopes(): string
    {
        return 'openid email https://www.googleapis.com/auth/calendar';
    }

    protected function authorizeParameters(): array
    {
        // A refresh token is only handed out with offline access, and again only when asked to consent.
        return ['access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true'];
    }

    protected function account(string $accessToken): ?string
    {
        $email = Http::timeout(20)->withToken($accessToken)->get('https://openidconnect.googleapis.com/v1/userinfo')->json('email');

        return is_string($email) ? $email : null;
    }

    public function calendars(CalendarConnection $connection): array
    {
        $items = $this->check($this->http($connection)->get(self::API.'/users/me/calendarList', ['minAccessRole' => 'writer']))->json('items') ?? [];

        $calendars = [];
        foreach ($items as $item) {
            $calendars[] = ['id' => (string) $item['id'], 'name' => (string) ($item['summaryOverride'] ?? $item['summary'] ?? $item['id']), 'primary' => (bool) ($item['primary'] ?? false)];
        }

        return $calendars;
    }

    public function events(CalendarConnection $connection, string $from, string $to): array
    {
        $events = [];
        $page = null;

        do {
            $response = $this->check($this->http($connection)->get($this->eventsUrl($connection), array_filter([
                'timeMin' => CarbonImmutable::parse($from, Dates::ZONE)->toIso8601String(),
                'timeMax' => CarbonImmutable::parse($to, Dates::ZONE)->endOfDay()->toIso8601String(),
                // Repeating events once each, with their rule, rather than every occurrence.
                'singleEvents' => 'false',
                'maxResults' => 250,
                'pageToken' => $page,
            ])));

            foreach ($response->json('items') ?? [] as $item) {
                // A changed or cancelled single occurrence of a repeating event can't be shown on its own here.
                if (($item['status'] ?? '') === 'cancelled' || isset($item['recurringEventId'])) {
                    continue;
                }
                if (($event = $this->read($item)) !== null) {
                    $events[] = $event;
                }
            }

            $page = $response->json('nextPageToken');
        } while (is_string($page) && count($events) < 2000);

        return $events;
    }

    public function create(CalendarConnection $connection, EventData $data, int $tribeId): RemoteEvent
    {
        $item = $this->check($this->http($connection)->post($this->eventsUrl($connection), $this->body($data, $tribeId)))->json();

        return $this->read($item) ?? throw new \RuntimeException(__('Google het ’n onverwagte antwoord gegee.'));
    }

    public function update(CalendarConnection $connection, RemoteEvent $remote, EventData $data, int $tribeId): RemoteEvent
    {
        // PATCH, so whatever else is on the event there (guests, reminders) stays as it is.
        $item = $this->check($this->http($connection)->patch($this->eventsUrl($connection).'/'.rawurlencode($remote->id), $this->body($data, $tribeId)))->json();

        return $this->read($item) ?? throw new \RuntimeException(__('Google het ’n onverwagte antwoord gegee.'));
    }

    public function delete(CalendarConnection $connection, string $remoteId): void
    {
        $this->check($this->http($connection)->delete($this->eventsUrl($connection).'/'.rawurlencode($remoteId)), [404, 410]);
    }

    private function eventsUrl(CalendarConnection $connection): string
    {
        return self::API.'/calendars/'.rawurlencode((string) $connection->calendar_id).'/events';
    }

    /** @param  array<string, mixed>  $item */
    private function read(array $item): ?RemoteEvent
    {
        $start = $item['start'] ?? [];
        $end = $item['end'] ?? [];
        $allDay = isset($start['date']);

        try {
            $startAt = $allDay ? CarbonImmutable::parse($start['date'], Dates::ZONE) : CarbonImmutable::parse($start['dateTime'] ?? '');
            $endAt = isset($end['date']) ? CarbonImmutable::parse($end['date'], Dates::ZONE)
                : (isset($end['dateTime']) ? CarbonImmutable::parse($end['dateTime']) : null);
        } catch (\Throwable) {
            return null;
        }

        $rrule = null;
        foreach ($item['recurrence'] ?? [] as $line) {
            if (str_starts_with((string) $line, 'RRULE:')) {
                $rrule = substr((string) $line, 6);
            }
        }
        [$repeats, $until] = $this->parser->rule($rrule, $startAt->setTimezone(Dates::ZONE));

        $marker = $item['extendedProperties']['private']['tribe'] ?? null;

        return new RemoteEvent(
            (string) $item['id'],
            (string) ($item['etag'] ?? ''),
            EventData::fromMoments((string) ($item['summary'] ?? ''), $startAt, $endAt, $allDay, $item['location'] ?? null, isset($item['description']) ? strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", (string) $item['description'])) : null, $repeats, $until),
            isset($item['updated']) ? CarbonImmutable::parse($item['updated']) : null,
            TwoWaySync::tribeId(is_string($marker) ? $marker : null),
        );
    }

    /** @return array<string, mixed> */
    private function body(EventData $data, int $tribeId): array
    {
        $rule = $data->rrule();

        return [
            'summary' => $data->title,
            'location' => $data->location ?? '',
            'description' => $data->notes ?? '',
            'start' => $data->isAllDay()
                ? ['date' => $data->startsOn]
                : ['dateTime' => $data->start()->format('Y-m-d\TH:i:s'), 'timeZone' => Dates::ZONE],
            'end' => $data->isAllDay()
                ? ['date' => $data->end()->toDateString()]
                : ['dateTime' => $data->end()->format('Y-m-d\TH:i:s'), 'timeZone' => Dates::ZONE],
            'recurrence' => $rule === null ? [] : ['RRULE:'.$rule],
            'extendedProperties' => ['private' => ['tribe' => TwoWaySync::marker($tribeId)]],
        ];
    }
}
