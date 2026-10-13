<?php

namespace App\Services\Sync;

use App\Models\CalendarConnection;
use App\Models\Event;
use App\Support\Ics\IcsParser;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** Outlook and Microsoft 365 through Microsoft Graph. */
class OutlookCalendar extends OAuthProvider
{
    private const API = 'https://graph.microsoft.com/v1.0';

    /** Graph's (Windows) name for Africa/Johannesburg. */
    private const ZONE = 'South Africa Standard Time';

    /** Where Tribe notes which Tribe event an Outlook event is a copy of. */
    private const MARKER = 'String {6b1c0f1e-3a5d-4f0b-9c64-2f1d8e7a9b30} Name tribe';

    public function __construct(private IcsParser $parser) {}

    protected function key(): string
    {
        return CalendarConnection::OUTLOOK;
    }

    private function tenant(): string
    {
        return (string) (config('services.outlook.tenant') ?: 'common');
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://login.microsoftonline.com/'.$this->tenant().'/oauth2/v2.0/authorize';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://login.microsoftonline.com/'.$this->tenant().'/oauth2/v2.0/token';
    }

    protected function scopes(): string
    {
        return 'offline_access User.Read Calendars.ReadWrite';
    }

    protected function authorizeParameters(): array
    {
        return ['prompt' => 'select_account'];
    }

    protected function account(string $accessToken): ?string
    {
        $me = Http::timeout(20)->withToken($accessToken)->get(self::API.'/me', ['$select' => 'mail,userPrincipalName'])->json();
        $email = $me['mail'] ?? $me['userPrincipalName'] ?? null;

        return is_string($email) ? $email : null;
    }

    protected function http(CalendarConnection $connection): PendingRequest
    {
        return parent::http($connection)->withHeaders([
            'Prefer' => 'outlook.timezone="'.self::ZONE.'", outlook.body-content-type="text"',
        ]);
    }

    public function calendars(CalendarConnection $connection): array
    {
        $items = $this->check($this->http($connection)->get(self::API.'/me/calendars', ['$select' => 'id,name,canEdit,isDefaultCalendar', '$top' => 100]))->json('value') ?? [];

        $calendars = [];
        foreach ($items as $item) {
            if ($item['canEdit'] ?? false) {
                $calendars[] = ['id' => (string) $item['id'], 'name' => (string) $item['name'], 'primary' => (bool) ($item['isDefaultCalendar'] ?? false)];
            }
        }

        return $calendars;
    }

    public function events(CalendarConnection $connection, string $from, string $to): array
    {
        $events = [];
        $url = self::API.'/me/calendars/'.rawurlencode((string) $connection->calendar_id).'/events?'.http_build_query([
            '$top' => 100,
            '$select' => 'id,subject,body,start,end,isAllDay,location,recurrence,changeKey,lastModifiedDateTime,type,isCancelled',
            '$expand' => "singleValueExtendedProperties(\$filter=id eq '".self::MARKER."')",
        ]);

        // /events gives single events and the first of each repeating series, never every occurrence.
        for ($page = 0; is_string($url) && $page < 30; $page++) {
            $response = $this->check($this->http($connection)->get($url));

            foreach ($response->json('value') ?? [] as $item) {
                if (($item['isCancelled'] ?? false) || ! in_array($item['type'] ?? 'singleInstance', ['singleInstance', 'seriesMaster'], true)) {
                    continue;
                }
                $event = $this->read($item);
                if ($event !== null && $event->data->overlaps($from, $to)) {
                    $events[] = $event;
                }
            }

            $url = $response->json('@odata.nextLink');
        }

        return $events;
    }

    public function create(CalendarConnection $connection, EventData $data, int $tribeId): RemoteEvent
    {
        $item = $this->check($this->http($connection)->post(self::API.'/me/calendars/'.rawurlencode((string) $connection->calendar_id).'/events', $this->body($data, $tribeId)))->json();

        return $this->read($item) ?? throw new \RuntimeException(__('Outlook het ’n onverwagte antwoord gegee.'));
    }

    public function update(CalendarConnection $connection, RemoteEvent $remote, EventData $data, int $tribeId): RemoteEvent
    {
        $item = $this->check($this->http($connection)->patch(self::API.'/me/events/'.rawurlencode($remote->id), $this->body($data, $tribeId)))->json();

        return $this->read($item) ?? throw new \RuntimeException(__('Outlook het ’n onverwagte antwoord gegee.'));
    }

    public function delete(CalendarConnection $connection, string $remoteId): void
    {
        $response = $this->http($connection)->delete(self::API.'/me/events/'.rawurlencode($remoteId));

        if ($response->status() !== 404) {
            $this->check($response);
        }
    }

    /** @param  array<string, mixed>  $item */
    private function read(array $item): ?RemoteEvent
    {
        if (empty($item['id']) || empty($item['start']['dateTime'])) {
            return null;
        }

        $allDay = (bool) ($item['isAllDay'] ?? false);
        try {
            $start = $this->moment($item['start']);
            $end = empty($item['end']['dateTime']) ? null : $this->moment($item['end']);
        } catch (\Throwable) {
            return null;
        }
        if ($allDay) {
            $start = CarbonImmutable::parse($start->toDateString(), EventData::ZONE);
            $end = $end === null ? null : CarbonImmutable::parse($end->toDateString(), EventData::ZONE);
        }

        [$repeats, $until] = $this->parser->rule($this->rrule($item['recurrence'] ?? null), $start->setTimezone(EventData::ZONE));

        $marker = null;
        foreach ($item['singleValueExtendedProperties'] ?? [] as $property) {
            if (strcasecmp((string) ($property['id'] ?? ''), self::MARKER) === 0) {
                $marker = (string) ($property['value'] ?? '');
            }
        }

        return new RemoteEvent(
            (string) $item['id'],
            (string) ($item['changeKey'] ?? ''),
            EventData::fromMoments((string) ($item['subject'] ?? ''), $start, $end, $allDay, $item['location']['displayName'] ?? null, $item['body']['content'] ?? null, $repeats, $until),
            isset($item['lastModifiedDateTime']) ? CarbonImmutable::parse($item['lastModifiedDateTime']) : null,
            TwoWaySync::tribeId($marker),
        );
    }

    /** @param  array{dateTime: string, timeZone?: string}  $moment */
    private function moment(array $moment): CarbonImmutable
    {
        $zone = $moment['timeZone'] ?? self::ZONE;
        $zone = $zone === self::ZONE ? EventData::ZONE : $zone;

        try {
            new \DateTimeZone($zone);
        } catch (\Throwable) {
            $zone = 'UTC';
        }

        return CarbonImmutable::parse(substr($moment['dateTime'], 0, 19), $zone);
    }

    /**
     * Outlook's repeat pattern as an iCalendar rule, so it is read the same way as everywhere else.
     *
     * @param  array<string, mixed>|null  $recurrence
     */
    private function rrule(?array $recurrence): ?string
    {
        $pattern = $recurrence['pattern'] ?? null;
        $range = $recurrence['range'] ?? [];
        if (! is_array($pattern)) {
            return null;
        }

        $freq = match ($pattern['type'] ?? '') {
            'daily' => 'DAILY',
            'weekly' => 'WEEKLY',
            'absoluteMonthly' => 'MONTHLY',
            'absoluteYearly' => 'YEARLY',
            // "The second Sunday of the month" and the like: not something Tribe repeats.
            default => null,
        };
        if ($freq === null) {
            return null;
        }

        $rule = 'FREQ='.$freq.';INTERVAL='.max(1, (int) ($pattern['interval'] ?? 1));
        if ($freq === 'WEEKLY' && ! empty($pattern['daysOfWeek'])) {
            $rule .= ';BYDAY='.implode(',', array_map(fn ($day) => strtoupper(substr((string) $day, 0, 2)), $pattern['daysOfWeek']));
        }
        if (($range['type'] ?? '') === 'endDate' && ! empty($range['endDate'])) {
            $rule .= ';UNTIL='.str_replace('-', '', (string) $range['endDate']);
        } elseif (($range['type'] ?? '') === 'numbered' && ! empty($range['numberOfOccurrences'])) {
            $rule .= ';COUNT='.(int) $range['numberOfOccurrences'];
        }

        return $rule;
    }

    /** @return array<string, mixed> */
    private function body(EventData $data, int $tribeId): array
    {
        $start = $data->start();
        $moment = fn (CarbonImmutable $at) => ['dateTime' => $at->format('Y-m-d\TH:i:s'), 'timeZone' => self::ZONE];

        $recurrence = null;
        if ($data->repeats !== Event::ONCE) {
            $pattern = match ($data->repeats) {
                Event::WEEKLY, Event::FORTNIGHTLY => ['type' => 'weekly', 'interval' => $data->repeats === Event::WEEKLY ? 1 : 2, 'daysOfWeek' => [strtolower($start->locale('en')->englishDayOfWeek)], 'firstDayOfWeek' => 'monday'],
                Event::MONTHLY => ['type' => 'absoluteMonthly', 'interval' => 1, 'dayOfMonth' => $start->day],
                default => ['type' => 'absoluteYearly', 'interval' => 1, 'dayOfMonth' => $start->day, 'month' => $start->month],
            };
            $recurrence = [
                'pattern' => $pattern,
                'range' => $data->repeatUntil === null
                    ? ['type' => 'noEnd', 'startDate' => $data->startsOn]
                    : ['type' => 'endDate', 'startDate' => $data->startsOn, 'endDate' => $data->repeatUntil],
            ];
        }

        return [
            'subject' => $data->title,
            'body' => ['contentType' => 'text', 'content' => $data->notes ?? ''],
            'location' => ['displayName' => $data->location ?? ''],
            'isAllDay' => $data->isAllDay(),
            'start' => $moment($start),
            'end' => $moment($data->end()),
            'recurrence' => $recurrence,
            'singleValueExtendedProperties' => [['id' => self::MARKER, 'value' => TwoWaySync::marker($tribeId)]],
        ];
    }
}
