<?php

use App\Models\CalendarFeed;
use App\Models\Event;
use App\Services\CalendarSync;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->me = adult(household('Ons'));
});

function schoolIcs(string $title = 'Kwartaal 4 begin'): string
{
    $day = today()->addDays(10)->format('Ymd');

    return "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:k4\r\nSUMMARY:{$title}\r\nDTSTART;VALUE=DATE:{$day}\r\nEND:VEVENT\r\n"
        ."BEGIN:VEVENT\r\nUID:old\r\nSUMMARY:Lank gelede\r\nDTSTART;VALUE=DATE:20100101\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
}

it('subscribes to a calendar and copies its events', function () {
    Http::fake(['*' => Http::response(schoolIcs())]);

    $this->actingAs($this->me)->post(route('feeds.store'), [
        'name' => 'Skool', 'url' => 'webcal://93.184.215.14/skool.ics', 'share' => 'household',
    ])->assertRedirect(route('feeds.index'));

    $feed = CalendarFeed::query()->sole();
    expect($feed->household_id)->toBe($this->me->household_id)
        ->and($feed->last_error)->toBeNull()
        ->and($feed->events()->pluck('title')->all())->toBe(['Kwartaal 4 begin']);

    Http::assertSent(fn ($request) => $request->url() === 'https://93.184.215.14/skool.ics');
    $this->get(route('home'))->assertSee('Kwartaal 4 begin');
});

it('updates and removes events on the next sync', function () {
    $feed = CalendarFeed::query()->create(['name' => 'Skool', 'url' => 'https://93.184.215.14/s.ics']);
    Http::fakeSequence()->push(schoolIcs())->push(schoolIcs('Kwartaal 4 begin (verskuif)'))->push("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n");

    app(CalendarSync::class)->sync($feed);
    app(CalendarSync::class)->sync($feed);
    expect(Event::query()->pluck('title')->all())->toBe(['Kwartaal 4 begin (verskuif)']);

    app(CalendarSync::class)->sync($feed);
    expect(Event::query()->count())->toBe(0);
});

it('keeps the reason when a calendar can’t be read', function () {
    Http::fake(['*' => Http::response('<html>Not a calendar</html>')]);
    $feed = CalendarFeed::query()->create(['name' => 'Stukkend', 'url' => 'https://93.184.215.14/x']);

    expect(app(CalendarSync::class)->sync($feed))->toBeFalse()
        ->and($feed->fresh()->last_error)->toContain('.ics');
});

it('does not let a subscribed event be edited in Tribe', function () {
    $feed = CalendarFeed::query()->create(['name' => 'Skool', 'url' => 'https://93.184.215.14/s.ics']);
    $event = Event::query()->create(['title' => 'Sportdag', 'starts_on' => today(), 'feed_id' => $feed->id, 'external_uid' => 'x']);

    $this->actingAs($this->me);
    $this->get(route('events.show', $event))->assertOk()->assertSee('ingetekende kalender')->assertDontSee(route('events.edit', $event));
    $this->get(route('events.edit', $event))->assertForbidden();
    $this->delete(route('events.destroy', $event))->assertForbidden();
});

it('hides another household’s subscribed calendar', function () {
    $feed = CalendarFeed::query()->create(['name' => 'Hulle skool', 'url' => 'https://93.184.215.14/s.ics', 'household_id' => household('Hulle')->id]);

    $this->actingAs($this->me)->get(route('feeds.index'))->assertDontSee('Hulle skool');
    $this->actingAs($this->me)->delete(route('feeds.destroy', $feed))->assertNotFound();
});

it('publishes what the member sees as a calendar feed', function () {
    $this->me->update(['birthday' => '1990-03-15']);
    Event::query()->create(['title' => 'Rugby', 'starts_on' => today(), 'start_time' => '15:00', 'repeats' => Event::WEEKLY, 'household_id' => $this->me->household_id]);
    Event::query()->create(['title' => 'Hulle geheim', 'starts_on' => today(), 'household_id' => household('Hulle')->id]);

    $token = $this->me->calendarToken();
    $response = $this->get(route('ics', ['token' => $token]))->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/calendar');
    expect($response->getContent())
        ->toContain('SUMMARY:Rugby')
        ->toContain('RRULE:FREQ=WEEKLY')
        ->toContain('verjaar')
        ->not->toContain('Hulle geheim');
});

it('refuses an unknown or replaced calendar link', function () {
    $old = $this->me->calendarToken();
    $this->get(route('ics', ['token' => str_repeat('a', 40)]))->assertNotFound();

    $this->actingAs($this->me)->post(route('feeds.new-link'));
    $this->get(route('ics', ['token' => $old]))->assertNotFound();
});
