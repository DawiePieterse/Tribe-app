<?php

use App\Models\CalendarConnection;
use App\Models\Event;
use App\Services\Sync\EventData;
use App\Services\Sync\GoogleCalendar;
use App\Services\Sync\ICloudCalendar;
use App\Services\Sync\OutlookCalendar;
use App\Services\Sync\TwoWaySync;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.google.client_id' => 'gid', 'services.google.client_secret' => 'gsecret',
        'services.outlook.client_id' => 'mid', 'services.outlook.client_secret' => 'msecret',
    ]);
    $this->me = adult(household('Ons'));
});

function connection(string $provider, array $credentials = ['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => PHP_INT_MAX]): CalendarConnection
{
    return CalendarConnection::query()->create([
        'member_id' => test()->me->id, 'provider' => $provider, 'credentials' => $credentials,
        'calendar_id' => $provider === 'icloud' ? 'https://p52-caldav.icloud.com/123/calendars/home/' : 'cal@x',
    ]);
}

it('reads Google events: all-day, timed, repeating and its own copies', function () {
    $day = today()->addDays(3)->toDateString();
    Http::fake(['www.googleapis.com/calendar/v3/calendars/*' => Http::response(['items' => [
        ['id' => 'a', 'etag' => '"1"', 'summary' => 'Vakansie', 'start' => ['date' => $day], 'end' => ['date' => today()->addDays(6)->toDateString()]],
        ['id' => 'b', 'etag' => '"2"', 'summary' => 'Tennis', 'start' => ['dateTime' => $day.'T07:00:00Z'], 'end' => ['dateTime' => $day.'T08:30:00Z'],
            'recurrence' => ['RRULE:FREQ=WEEKLY;UNTIL=20271231T000000Z'], 'extendedProperties' => ['private' => ['tribe' => TwoWaySync::marker(7)]]],
        ['id' => 'c', 'status' => 'cancelled'],
        ['id' => 'd', 'recurringEventId' => 'b', 'summary' => 'Een keer verskuif', 'start' => ['date' => $day], 'end' => ['date' => $day]],
    ]])]);

    $events = app(GoogleCalendar::class)->events(connection('google'), today()->toDateString(), today()->addYear()->toDateString());

    expect($events)->toHaveCount(2)
        ->and($events[0]->data)->toEqual(EventData::make('Vakansie', $day, today()->addDays(5)->toDateString()))
        ->and($events[1]->data->startTime)->toBe('09:00')
        ->and($events[1]->data->endTime)->toBe('10:30')
        ->and($events[1]->data->repeats)->toBe(Event::WEEKLY)
        ->and($events[1]->data->repeatUntil)->toBe('2027-12-31')
        ->and($events[1]->tribeId)->toBe(7)
        ->and($events[1]->version)->toBe('"2"');
});

it('writes Google events with local times, the repeat rule and a Tribe marker', function () {
    Http::fake(['*' => fn (Request $r) => Http::response(['id' => 'new', 'etag' => '"9"'] + $r->data())]);

    $remote = app(GoogleCalendar::class)->create(connection('google'), EventData::make('Rugby', '2026-11-07', null, '15:00', null, null, null, Event::FORTNIGHTLY, null, ['2026-11-21']), 12);

    Http::assertSent(function (Request $r) {
        return $r->method() === 'POST'
            && $r->hasHeader('Authorization', 'Bearer tok')
            && $r['start'] === ['dateTime' => '2026-11-07T15:00:00', 'timeZone' => 'Africa/Johannesburg']
            && $r['end']['dateTime'] === '2026-11-07T16:00:00'
            && $r['recurrence'] === ['RRULE:FREQ=WEEKLY;INTERVAL=2', 'EXDATE;TZID=Africa/Johannesburg:20261121T150000']
            && $r['extendedProperties']['private']['tribe'] === TwoWaySync::marker(12);
    });
    expect($remote->id)->toBe('new')->and($remote->version)->toBe('"9"');
});

it('refreshes an expired Google token and keeps the refresh token', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh', 'expires_in' => 3600]),
        'www.googleapis.com/*' => Http::response(['items' => []]),
    ]);
    $connection = connection('google', ['access_token' => 'old', 'refresh_token' => 'ref', 'expires_at' => time() - 10]);

    app(GoogleCalendar::class)->calendars($connection);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'calendarList') && $r->hasHeader('Authorization', 'Bearer fresh'));
    expect($connection->fresh()->credentials['refresh_token'])->toBe('ref')
        ->and($connection->fresh()->credentials['access_token'])->toBe('fresh');
});

it('reads and writes Outlook events', function () {
    $day = today()->addDays(2)->toDateString();
    Http::fake([
        'graph.microsoft.com/v1.0/me/calendars/*' => Http::response(['value' => [
            ['id' => 'o1', 'changeKey' => 'k1', 'subject' => 'Kerk', 'type' => 'seriesMaster', 'isAllDay' => false,
                'start' => ['dateTime' => $day.'T09:00:00.0000000', 'timeZone' => 'South Africa Standard Time'],
                'end' => ['dateTime' => $day.'T10:00:00.0000000', 'timeZone' => 'South Africa Standard Time'],
                'location' => ['displayName' => 'NG Kerk'], 'body' => ['content' => 'Bring Bybel'],
                'recurrence' => ['pattern' => ['type' => 'weekly', 'interval' => 1, 'daysOfWeek' => [strtolower(today()->addDays(2)->locale('en')->englishDayOfWeek)]], 'range' => ['type' => 'noEnd']]],
            ['id' => 'o2', 'changeKey' => 'k2', 'subject' => 'Verjaarsdag', 'type' => 'singleInstance', 'isAllDay' => true,
                'start' => ['dateTime' => $day.'T00:00:00.0000000', 'timeZone' => 'South Africa Standard Time'],
                'end' => ['dateTime' => today()->addDays(3)->toDateString().'T00:00:00.0000000', 'timeZone' => 'South Africa Standard Time']],
            ['id' => 'o3', 'type' => 'singleInstance', 'isCancelled' => true, 'start' => ['dateTime' => $day.'T00:00:00']],
        ]]),
        'graph.microsoft.com/v1.0/me/events/*' => Http::response(['id' => 'o1', 'changeKey' => 'k3', 'subject' => 'Kerk', 'start' => ['dateTime' => $day.'T09:00:00'], 'end' => ['dateTime' => $day.'T10:00:00']]),
    ]);
    $outlook = app(OutlookCalendar::class);
    $connection = connection('outlook');

    $events = $outlook->events($connection, today()->toDateString(), today()->addYear()->toDateString());

    expect($events)->toHaveCount(2)
        ->and($events[0]->data)->toEqual(EventData::make('Kerk', $day, null, '09:00', '10:00', 'NG Kerk', 'Bring Bybel', Event::WEEKLY))
        ->and($events[1]->data)->toEqual(EventData::make('Verjaarsdag', $day));

    $outlook->update($connection, $events[0], EventData::make('Kerk', $day, null, '09:00', '10:30', null, null, Event::MONTHLY), 3);

    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
        && $r['recurrence']['pattern'] === ['type' => 'absoluteMonthly', 'interval' => 1, 'dayOfMonth' => today()->addDays(2)->day]
        && $r['end'] === ['dateTime' => $day.'T10:30:00', 'timeZone' => 'South Africa Standard Time']
        && $r['singleValueExtendedProperties'][0]['value'] === TwoWaySync::marker(3)
        && str_contains($r->header('Prefer')[0], 'South Africa Standard Time'));
});

it('finds iCloud calendars and syncs events as .ics files', function () {
    $day = today()->addDays(4)->format('Ymd');
    Http::fake(function (Request $r) use ($day) {
        $body = $r->body();

        return match (true) {
            $r->method() === 'PROPFIND' && str_contains($body, 'current-user-principal') => Http::response('<d:multistatus xmlns:d="DAV:"><d:response><d:propstat><d:prop><d:current-user-principal><d:href>/123/principal/</d:href></d:current-user-principal></d:prop></d:propstat></d:response></d:multistatus>', 207),
            $r->method() === 'PROPFIND' && str_contains($body, 'calendar-home-set') => Http::response('<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:response><d:propstat><d:prop><c:calendar-home-set><d:href>https://p52-caldav.icloud.com:443/123/calendars/</d:href></c:calendar-home-set></d:prop></d:propstat></d:response></d:multistatus>', 207),
            $r->method() === 'PROPFIND' => Http::response('<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">'
                .'<d:response><d:href>/123/calendars/</d:href><d:propstat><d:prop><d:resourcetype><d:collection/></d:resourcetype></d:prop></d:propstat></d:response>'
                .'<d:response><d:href>/123/calendars/home/</d:href><d:propstat><d:prop><d:displayname>Huis</d:displayname><d:resourcetype><d:collection/><c:calendar/></d:resourcetype><c:supported-calendar-component-set><c:comp name="VEVENT"/></c:supported-calendar-component-set></d:prop></d:propstat></d:response>'
                .'<d:response><d:href>/123/calendars/tasks/</d:href><d:propstat><d:prop><d:displayname>Take</d:displayname><d:resourcetype><d:collection/><c:calendar/></d:resourcetype><c:supported-calendar-component-set><c:comp name="VTODO"/></c:supported-calendar-component-set></d:prop></d:propstat></d:response>'
                .'</d:multistatus>', 207),
            $r->method() === 'REPORT' => Http::response('<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:response><d:href>/123/calendars/home/abc.ics</d:href><d:propstat><d:prop><d:getetag>"e1"</d:getetag>'
                ."<c:calendar-data>BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:event-".TwoWaySync::marker(5)."\r\nSUMMARY:Piekniek\r\nDTSTART;VALUE=DATE:{$day}\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n</c:calendar-data>"
                .'</d:prop></d:propstat></d:response></d:multistatus>', 207),
            $r->method() === 'PUT' => Http::response('', 201, ['ETag' => '"e2"']),
            default => Http::response('', 404),
        };
    });
    $icloud = app(ICloudCalendar::class);

    $result = $icloud->connect('ma@example.com', 'abcd-efgh-ijkl-mnop');
    expect($result['credentials']['home'])->toBe('https://p52-caldav.icloud.com:443/123/calendars/');

    $connection = connection('icloud', $result['credentials']);
    expect($icloud->calendars($connection))->toBe([['id' => 'https://p52-caldav.icloud.com:443/123/calendars/home/', 'name' => 'Huis', 'primary' => true]]);

    $events = $icloud->events($connection, today()->toDateString(), today()->addYear()->toDateString());
    expect($events)->toHaveCount(1)
        ->and($events[0]->id)->toBe('https://p52-caldav.icloud.com/123/calendars/home/abc.ics')
        ->and($events[0]->tribeId)->toBe(5)
        ->and($events[0]->data->title)->toBe('Piekniek');

    $created = $icloud->create($connection, EventData::make('Braai', '2026-12-01', null, '18:00', '21:00'), 8);
    expect($created->version)->toBe('"e2"');

    Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
        && $r->hasHeader('If-None-Match', '*')
        && str_contains($r->body(), 'UID:event-'.TwoWaySync::marker(8))
        && str_contains($r->body(), 'DTSTART:20261201T160000Z')
        && ! str_contains($r->body(), 'METHOD:'));
});

it('only talks to Apple’s servers with an iCloud password', function () {
    Http::fake(['*' => Http::response('<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:response><d:propstat><d:prop><d:current-user-principal><d:href>/p/</d:href></d:current-user-principal><c:calendar-home-set><d:href>https://evil.example.com/cal/</d:href></c:calendar-home-set></d:prop></d:propstat></d:response></d:multistatus>', 207)]);

    expect(fn () => app(ICloudCalendar::class)->connect('ma@example.com', 'x'))->toThrow(RuntimeException::class);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'evil.example.com'));
});

it('tells iCloud sign-in mistakes plainly', function () {
    Http::fake(['*' => Http::response('', 401)]);

    $this->actingAs($this->me)->post(route('connections.icloud.store'), ['apple_id' => 'ma@example.com', 'password' => 'verkeerd'])
        ->assertSessionHasErrors(['password' => __('iCloud het die Apple ID of app-wagwoord nie aanvaar nie.')]);
    expect(CalendarConnection::query()->count())->toBe(0);
});

it('signs in with Google and checks the state that comes back', function () {
    $this->actingAs($this->me);

    // A forged callback goes nowhere.
    $this->get(route('connections.connect', 'google'));
    $this->get(route('connections.callback', ['provider' => 'google', 'code' => 'c', 'state' => 'nope']))->assertRedirect(route('feeds.index'));
    expect(CalendarConnection::query()->count())->toBe(0);

    $redirect = $this->get(route('connections.connect', 'google'))->assertRedirect()->headers->get('Location');
    expect($redirect)->toStartWith('https://accounts.google.com/')->toContain('access_type=offline')->toContain('client_id=gid');
    parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $query);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600]),
        'openidconnect.googleapis.com/*' => Http::response(['email' => 'ma@gmail.com']),
    ]);

    $response = $this->get(route('connections.callback', ['provider' => 'google', 'code' => 'c', 'state' => $query['state']]));

    $connection = CalendarConnection::query()->sole();
    $response->assertRedirect(route('connections.edit', $connection));

    expect($connection->account)->toBe('ma@gmail.com')
        ->and($connection->member_id)->toBe($this->me->id)
        ->and($connection->credentials['refresh_token'])->toBe('r');
});

it('offers only the calendars that are set up', function () {
    config(['services.outlook.client_id' => null]);

    $this->actingAs($this->me)->get(route('feeds.index'))
        ->assertSee(__('Koppel :provider', ['provider' => 'Google']))
        ->assertSee(__('Koppel :provider', ['provider' => 'iCloud']))
        ->assertDontSee(__('Koppel :provider', ['provider' => 'Outlook']));
    $this->actingAs($this->me)->get(route('connections.connect', 'outlook'))->assertNotFound();
});
