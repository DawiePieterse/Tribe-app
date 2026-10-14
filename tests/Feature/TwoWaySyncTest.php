<?php

use App\Models\CalendarConnection;
use App\Models\CalendarFeed;
use App\Models\Event;
use App\Models\EventLink;
use App\Services\Sync\CalendarProvider;
use App\Services\Sync\EventData;
use App\Services\Sync\ProviderFactory;
use App\Services\Sync\RemoteEvent;
use App\Services\Sync\TwoWaySync;
use Carbon\CarbonImmutable;

/** A calendar that lives in memory, standing in for Google, Outlook or iCloud. */
class FakeCalendar implements CalendarProvider
{
    /** @var array<string, RemoteEvent> */
    public array $events = [];

    private int $n = 0;

    public function calendars(CalendarConnection $connection): array
    {
        return [['id' => 'cal-1', 'name' => 'Persoonlik', 'primary' => true]];
    }

    public function events(CalendarConnection $connection, string $from, string $to): array
    {
        return array_values($this->events);
    }

    public function create(CalendarConnection $connection, EventData $data, int $tribeId): RemoteEvent
    {
        $id = 'r'.(++$this->n);

        return $this->events[$id] = new RemoteEvent($id, 'v1', $data, CarbonImmutable::now(), $tribeId);
    }

    public function update(CalendarConnection $connection, RemoteEvent $remote, EventData $data, int $tribeId): RemoteEvent
    {
        return $this->events[$remote->id] = new RemoteEvent($remote->id, $remote->version.'+', $data, CarbonImmutable::now(), $tribeId);
    }

    public function delete(CalendarConnection $connection, string $remoteId): void
    {
        unset($this->events[$remoteId]);
    }

    /** Someone adds or edits it in their own calendar app. */
    public function put(string $id, string $title, ?CarbonImmutable $at = null, ?int $tribeId = null): void
    {
        $version = isset($this->events[$id]) ? $this->events[$id]->version.'*' : 'v1';
        $this->events[$id] = new RemoteEvent($id, $version, EventData::make($title, ($at ?? today()->addDays(3))->toDateString(), null, '09:00', '10:00'), CarbonImmutable::now(), $tribeId);
    }
}

/** Hands every provider name the same fake calendar. */
class FakeProviders extends ProviderFactory
{
    public function __construct(private CalendarProvider $fake) {}

    public function for(string $provider): CalendarProvider
    {
        return $this->fake;
    }
}

beforeEach(function () {
    $this->ours = household('Ons');
    $this->other = household('Ander', '#C2185B');
    $this->me = adult($this->ours);
    $this->calendar = new FakeCalendar;
    app()->instance(ProviderFactory::class, new FakeProviders($this->calendar));

    $this->connection = CalendarConnection::query()->create([
        'member_id' => $this->me->id, 'provider' => 'google', 'credentials' => ['access_token' => 'x'],
        'calendar_id' => 'cal-1', 'calendar_name' => 'Persoonlik', 'household_id' => $this->ours->id,
    ]);
    $this->sync = fn () => app(TwoWaySync::class)->sync($this->connection->fresh());
});

it('brings events from their calendar into Tribe for their household', function () {
    $this->calendar->put('g1', 'Tandarts');

    expect(($this->sync)())->toBeTrue();

    $event = Event::query()->sole();
    expect($event->title)->toBe('Tandarts')
        ->and($event->start_time)->toStartWith('09:00')
        ->and($event->household_id)->toBe($this->ours->id)
        ->and($event->member_id)->toBe($this->me->id)
        ->and(EventLink::query()->sole()->origin)->toBe(EventLink::FROM_REMOTE)
        ->and($this->connection->fresh()->last_error)->toBeNull();

    ($this->sync)();
    expect(Event::query()->count())->toBe(1);
});

it('sends what they see in Tribe, and nothing private to another household', function () {
    Event::query()->create(['title' => 'Sondagete', 'starts_on' => today()->addWeek()]);
    Event::query()->create(['title' => 'Ons s’n', 'starts_on' => today()->addDay(), 'household_id' => $this->ours->id]);
    Event::query()->create(['title' => 'Hulle s’n', 'starts_on' => today()->addDay(), 'household_id' => $this->other->id]);
    Event::query()->create(['title' => 'Lank verby', 'starts_on' => today()->subYear()]);
    $feed = CalendarFeed::query()->create(['name' => 'Skool', 'url' => 'https://93.184.215.14/s.ics']);
    Event::query()->create(['title' => 'Uit die skool', 'starts_on' => today(), 'feed_id' => $feed->id, 'external_uid' => 'a']);

    ($this->sync)();

    $titles = collect($this->calendar->events)->map(fn ($e) => $e->data->title)->sort()->values()->all();
    expect($titles)->toBe(['Ons s’n', 'Sondagete']);

    ($this->sync)();
    expect($this->calendar->events)->toHaveCount(2);
});

it('sends only their own household’s events when asked', function () {
    $this->connection->update(['send' => CalendarConnection::SEND_OURS]);
    Event::query()->create(['title' => 'Familie', 'starts_on' => today()->addWeek()]);
    Event::query()->create(['title' => 'Ons s’n', 'starts_on' => today()->addDay(), 'household_id' => $this->ours->id]);

    ($this->sync)();

    expect(collect($this->calendar->events)->map(fn ($e) => $e->data->title)->values()->all())->toBe(['Ons s’n']);
});

it('carries changes across in both directions', function () {
    $this->calendar->put('g1', 'Tandarts');
    $tribe = Event::query()->create(['title' => 'Braai', 'starts_on' => today()->addDays(5), 'household_id' => $this->ours->id]);
    ($this->sync)();

    // Changed in their calendar.
    $this->calendar->put('g1', 'Tandarts (verskuif)', today()->addDays(4));
    // Changed in Tribe.
    $tribe->update(['title' => 'Braai by ons', 'location' => 'Agterplaas']);

    ($this->sync)();

    expect(Event::query()->where('title', 'Tandarts (verskuif)')->value('starts_on')->toDateString())->toBe(today()->addDays(4)->toDateString());
    $copy = collect($this->calendar->events)->first(fn ($e) => $e->tribeId === $tribe->id);
    expect($copy->data->title)->toBe('Braai by ons')
        ->and($copy->data->location)->toBe('Agterplaas');
});

it('lets the later change win when both sides changed', function () {
    $this->calendar->put('g1', 'Tandarts');
    ($this->sync)();
    $event = Event::query()->sole();

    $this->travel(1)->minutes();
    $event->update(['title' => 'Tandarts (Tribe)']);
    $this->travel(1)->minutes();
    $this->calendar->put('g1', 'Tandarts (kalender)');

    ($this->sync)();

    expect($event->fresh()->title)->toBe('Tandarts (kalender)')
        ->and($this->calendar->events['g1']->data->title)->toBe('Tandarts (kalender)');
});

it('deletes on the other side, but keeps a Tribe event someone took out of their own calendar', function () {
    $this->calendar->put('g1', 'Tandarts');
    $family = Event::query()->create(['title' => 'Sondagete', 'starts_on' => today()->addWeek()]);
    $mine = Event::query()->create(['title' => 'Braai', 'starts_on' => today()->addDays(2), 'household_id' => $this->ours->id]);
    ($this->sync)();
    $familyCopy = collect($this->calendar->events)->first(fn ($e) => $e->tribeId === $family->id)->id;

    unset($this->calendar->events['g1'], $this->calendar->events[$familyCopy]);
    $mine->delete();

    ($this->sync)();

    expect(Event::query()->pluck('title')->all())->toBe(['Sondagete'])
        ->and($this->calendar->events)->toBeEmpty();

    // Not sent to them again.
    ($this->sync)();
    expect($this->calendar->events)->toBeEmpty();
});

it('takes an event out of their calendar once they may no longer see it', function () {
    $event = Event::query()->create(['title' => 'Verrassing', 'starts_on' => today()->addWeek()]);
    ($this->sync)();
    expect($this->calendar->events)->toHaveCount(1);

    $event->update(['household_id' => $this->other->id]);
    ($this->sync)();

    expect($this->calendar->events)->toBeEmpty()
        ->and($event->fresh())->not->toBeNull();
});

it('recognises its own copies instead of bringing them in twice', function () {
    $event = Event::query()->create(['title' => 'Sondagete', 'starts_on' => today()->addWeek()]);
    $this->calendar->put('g9', 'Sondagete', today()->addWeek(), $event->id);

    ($this->sync)();

    expect(Event::query()->count())->toBe(1)
        ->and($this->calendar->events)->toHaveCount(1)
        ->and(EventLink::query()->sole()->event_id)->toBe($event->id);
});

it('keeps the reason when a sync fails', function () {
    $this->calendar = new class extends FakeCalendar
    {
        public function events(CalendarConnection $connection, string $from, string $to): array
        {
            throw new RuntimeException('Google het toegang geweier.');
        }
    };
    app()->instance(ProviderFactory::class, new FakeProviders($this->calendar));

    expect(($this->sync)())->toBeFalse()
        ->and($this->connection->fresh()->last_error)->toBe('Google het toegang geweier.');
});

it('syncs right after an event is added in Tribe', function () {
    $this->actingAs($this->me)->post(route('events.store'), [
        'kind' => 'event', 'title' => 'Dokter', 'starts_on' => today()->addDay()->toDateString(), 'share' => 'household',
    ])->assertRedirect();

    expect(collect($this->calendar->events)->map(fn ($e) => $e->data->title)->values()->all())->toBe(['Dokter']);
});

it('removes what came from a calendar when it is disconnected', function () {
    $this->calendar->put('g1', 'Tandarts');
    Event::query()->create(['title' => 'Sondagete', 'starts_on' => today()->addWeek()]);
    ($this->sync)();

    $this->actingAs($this->me)->delete(route('connections.destroy', $this->connection))->assertRedirect(route('feeds.index'));

    expect(Event::query()->pluck('title')->all())->toBe(['Sondagete'])
        ->and(CalendarConnection::query()->count())->toBe(0);
});

it('keeps connections private to their owner', function () {
    $someoneElse = adult($this->ours);

    $this->actingAs($someoneElse)->get(route('connections.edit', $this->connection))->assertNotFound();
    $this->actingAs($someoneElse)->post(route('connections.sync', $this->connection))->assertNotFound();
    $this->actingAs($someoneElse)->get(route('feeds.index'))->assertDontSee('Persoonlik');
    $this->actingAs($this->me)->get(route('feeds.index'))->assertOk()->assertSee('Persoonlik');
});

it('chooses the calendar and how it is shared, then syncs', function () {
    $this->connection->update(['calendar_id' => null]);
    $this->calendar->put('g1', 'Tandarts');

    $this->actingAs($this->me)->get(route('connections.edit', $this->connection))->assertOk()->assertSee('Persoonlik');
    $this->actingAs($this->me)->put(route('connections.update', $this->connection), [
        'calendar' => 'cal-1', 'share' => 'family', 'send' => 'all',
    ])->assertRedirect(route('feeds.index'));

    expect($this->connection->fresh()->household_id)->toBeNull()
        ->and(Event::query()->sole()->household_id)->toBeNull();
});

it('takes a skipped day out of the copy in their calendar too', function () {
    $event = Event::query()->create(['title' => 'Hokkie', 'starts_on' => today()->addDay(), 'start_time' => '15:00', 'repeats' => 'weekly', 'household_id' => $this->ours->id]);
    ($this->sync)();
    $copy = collect($this->calendar->events)->first();
    expect($copy->data->exdates)->toBe([]);

    $event->skips()->create(['date' => today()->addDays(8)->toDateString()]);
    ($this->sync)();

    $copy = collect($this->calendar->events)->first();
    expect($copy->data->exdates)->toBe([today()->addDays(8)->toDateString()])
        ->and($copy->data->exdateLines())->toBe(['EXDATE;TZID=Africa/Johannesburg:'.today()->addDays(8)->format('Ymd').'T150000']);
});
