<?php

namespace App\Services\Sync;

use App\Models\CalendarConnection;
use App\Support\Dates;
use App\Support\Ics\IcsParser;
use App\Support\Ics\IcsWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * iCloud over CalDAV, signed in with the Apple ID and an app-specific password (appleid.apple.com ›
 * Sign-In and Security › App-Specific Passwords). Each event is one .ics file in the calendar.
 */
class ICloudCalendar implements CalendarProvider
{
    public const SERVER = 'https://caldav.icloud.com/';

    public function __construct(private IcsParser $parser) {}

    /**
     * Checks the Apple ID and password and finds where its calendars live.
     *
     * @return array{account: string, credentials: array<string, mixed>}
     */
    public function connect(string $appleId, string $password): array
    {
        $credentials = ['username' => $appleId, 'password' => $password];
        $connection = new CalendarConnection(['credentials' => $credentials]);

        $principal = $this->href($this->propfind($connection, self::SERVER, 0, '<d:current-user-principal/>'), '//d:current-user-principal/d:href', self::SERVER);
        if ($principal === null) {
            throw new \RuntimeException(__('iCloud het nie gesê waar jou kalenders is nie.'));
        }

        $home = $this->href($this->propfind($connection, $principal, 0, '<c:calendar-home-set/>'), '//c:calendar-home-set/d:href', $principal);
        if ($home === null || ! $this->isICloud($home)) {
            throw new \RuntimeException(__('iCloud het nie gesê waar jou kalenders is nie.'));
        }

        return ['account' => $appleId, 'credentials' => $credentials + ['home' => $home]];
    }

    public function calendars(CalendarConnection $connection): array
    {
        $home = (string) ($connection->credentials['home'] ?? '');
        $xpath = $this->xpath($this->propfind($connection, $home, 1, '<d:displayname/><d:resourcetype/><c:supported-calendar-component-set/><d:current-user-privilege-set/>'));

        $calendars = [];
        foreach ($xpath->query('//d:response') ?: [] as $response) {
            $isCalendar = $xpath->evaluate('count(.//d:resourcetype/c:calendar)', $response) > 0;
            $hasEvents = $xpath->evaluate('count(.//c:supported-calendar-component-set/c:comp[@name="VEVENT"])', $response) > 0;
            $href = trim((string) $xpath->evaluate('string(d:href)', $response));

            if ($isCalendar && $hasEvents && $href !== '') {
                $name = trim((string) $xpath->evaluate('string(.//d:displayname)', $response));
                $calendars[] = ['id' => $this->absolute($href, $home), 'name' => $name !== '' ? $name : __('Kalender'), 'primary' => $calendars === []];
            }
        }

        return $calendars;
    }

    public function events(CalendarConnection $connection, string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from, Dates::ZONE)->utc()->format('Ymd\THis\Z');
        $end = CarbonImmutable::parse($to, Dates::ZONE)->endOfDay()->utc()->format('Ymd\THis\Z');

        $body = '<?xml version="1.0" encoding="utf-8"?>'
            .'<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">'
            .'<d:prop><d:getetag/><c:calendar-data/></d:prop>'
            .'<c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VEVENT">'
            .'<c:time-range start="'.$start.'" end="'.$end.'"/>'
            .'</c:comp-filter></c:comp-filter></c:filter></c:calendar-query>';

        $collection = (string) $connection->calendar_id;
        $response = $this->send($connection, 'REPORT', $collection, $body, ['Depth' => '1']);
        $xpath = $this->xpath($response);

        $events = [];
        foreach ($xpath->query('//d:response') ?: [] as $item) {
            $href = trim((string) $xpath->evaluate('string(d:href)', $item));
            $data = (string) $xpath->evaluate('string(.//c:calendar-data)', $item);
            if ($href === '' || $data === '') {
                continue;
            }

            // The event itself, not its changed occurrences (those have a RECURRENCE-ID, marked with #).
            $parsed = array_values(array_filter($this->parser->events($data), fn ($e) => ! str_contains($e['uid'], '#')))[0] ?? null;
            if ($parsed === null) {
                continue;
            }

            $events[] = new RemoteEvent(
                $this->absolute($href, $collection),
                trim((string) $xpath->evaluate('string(.//d:getetag)', $item)),
                EventData::make($parsed['title'], $parsed['starts_on'], $parsed['ends_on'], $parsed['start_time'], $parsed['end_time'], $parsed['location'], $parsed['notes'], $parsed['repeats'], $parsed['repeat_until']),
                null,
                TwoWaySync::tribeId(preg_match('/^event-(.+)$/', $parsed['uid'], $m) ? $m[1] : null),
            );
        }

        return $events;
    }

    public function create(CalendarConnection $connection, EventData $data, int $tribeId): RemoteEvent
    {
        $url = rtrim((string) $connection->calendar_id, '/').'/tribe-'.Str::uuid()->toString().'.ics';
        $response = $this->send($connection, 'PUT', $url, $this->ics($data, $tribeId), ['If-None-Match' => '*', 'Content-Type' => 'text/calendar; charset=utf-8']);

        return new RemoteEvent($url, (string) $response->header('ETag'), $data, null, $tribeId);
    }

    public function update(CalendarConnection $connection, RemoteEvent $remote, EventData $data, int $tribeId): RemoteEvent
    {
        $response = $this->send($connection, 'PUT', $remote->id, $this->ics($data, $tribeId), ['Content-Type' => 'text/calendar; charset=utf-8']);

        return new RemoteEvent($remote->id, (string) $response->header('ETag'), $data, null, $tribeId);
    }

    public function delete(CalendarConnection $connection, string $remoteId): void
    {
        $this->send($connection, 'DELETE', $remoteId, '', [], [404, 410]);
    }

    private function ics(EventData $data, int $tribeId): string
    {
        $ics = new IcsWriter(null);
        $ics->add([
            'uid' => 'event-'.TwoWaySync::marker($tribeId),
            'title' => $data->title,
            'date' => CarbonImmutable::parse($data->startsOn, Dates::ZONE),
            'end_date' => $data->endsOn !== null ? CarbonImmutable::parse($data->endsOn, Dates::ZONE) : null,
            'start_time' => $data->startTime,
            'end_time' => $data->endTime,
            'location' => $data->location,
            'notes' => $data->notes,
            'rrule' => $data->rrule(),
        ]);

        return $ics->render();
    }

    private function propfind(CalendarConnection $connection, string $url, int $depth, string $props): Response
    {
        $body = '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:prop>'.$props.'</d:prop></d:propfind>';

        return $this->send($connection, 'PROPFIND', $url, $body, ['Depth' => (string) $depth]);
    }

    /**
     * @param  array<string, string>  $headers
     * @param  list<int>  $alsoFine
     */
    private function send(CalendarConnection $connection, string $method, string $url, string $body, array $headers = [], array $alsoFine = []): Response
    {
        if (! $this->isICloud($url)) {
            throw new \RuntimeException(__('Dié adres is nie ’n iCloud-kalender nie.'));
        }

        $type = $headers['Content-Type'] ?? 'application/xml; charset=utf-8';
        unset($headers['Content-Type']);

        try {
            $response = $this->client($connection)->withHeaders($headers)->withBody($body, $type)->send($method, $url);
        } catch (\Throwable) {
            throw new \RuntimeException(__('iCloud het nie geantwoord nie.'));
        }

        if ($response->status() === 401) {
            throw new \RuntimeException(__('iCloud het die Apple ID of app-wagwoord nie aanvaar nie.'));
        }
        if (! $response->successful() && ! in_array($response->status(), $alsoFine, true)) {
            throw new \RuntimeException(__('iCloud het nie geantwoord nie (fout :status).', ['status' => $response->status()]));
        }

        return $response;
    }

    private function client(CalendarConnection $connection): PendingRequest
    {
        return Http::timeout(20)
            ->withBasicAuth((string) ($connection->credentials['username'] ?? ''), (string) ($connection->credentials['password'] ?? ''))
            ->withOptions(['allow_redirects' => ['max' => 3, 'strict' => true, 'protocols' => ['https']]]);
    }

    /** Only Apple's own servers: the address comes back from iCloud, and the password goes with it. */
    private function isICloud(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        return ($parts['scheme'] ?? '') === 'https' && ($host === 'caldav.icloud.com' || str_ends_with($host, '-caldav.icloud.com'));
    }

    private function href(Response $response, string $query, string $base): ?string
    {
        $href = trim((string) $this->xpath($response)->evaluate('string('.$query.')'));

        return $href === '' ? null : $this->absolute($href, $base);
    }

    private function absolute(string $href, string $base): string
    {
        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }

        $parts = parse_url($base);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '').'/'.ltrim($href, '/');
    }

    private function xpath(Response $response): \DOMXPath
    {
        $doc = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadXML($response->body() !== '' ? $response->body() : '<empty/>', LIBXML_NONET);
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('d', 'DAV:');
        $xpath->registerNamespace('c', 'urn:ietf:params:xml:ns:caldav');

        return $xpath;
    }
}
