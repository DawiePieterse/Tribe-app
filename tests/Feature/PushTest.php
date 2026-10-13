<?php

use App\Models\Event;
use App\Models\Member;
use App\Models\PushSubscription;
use App\Services\Push;

beforeEach(function () {
    Push::fake();
    $this->ours = household('Ons');
    $this->other = household('Ander', '#C2185B');
    $this->me = adult($this->ours);
    $this->them = adult($this->other);
});

function sentTo(Member $member): array
{
    return array_values(array_filter(Push::$sent, fn ($n) => $n['member_id'] === $member->id));
}

it('turns notifications on and off for a device', function () {
    $this->actingAs($this->me);

    $this->postJson(route('push.store'), ['endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertOk();
    $this->postJson(route('push.store'), ['endpoint' => 'http://push.example.com/abc', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertUnprocessable();
    expect(PushSubscription::query()->sole()->member_id)->toBe($this->me->id);

    // The same phone logging in as someone else moves to them.
    $this->actingAs($this->them)->postJson(route('push.store'), ['endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'p2', 'auth' => 'a2']])->assertOk();
    expect(PushSubscription::query()->sole()->member_id)->toBe($this->them->id);

    $this->actingAs($this->me)->deleteJson(route('push.destroy'), ['endpoint' => 'https://push.example.com/abc'])->assertOk();
    expect(PushSubscription::query()->count())->toBe(1);
    $this->actingAs($this->them)->deleteJson(route('push.destroy'), ['endpoint' => 'https://push.example.com/abc'])->assertOk();
    expect(PushSubscription::query()->count())->toBe(0);
});

it('shows the notifications switch only when keys are set', function () {
    config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
    Push::$sent = [];
    $this->actingAs($this->me)->get(route('settings'))->assertOk()->assertSee(__('Kennisgewings'));
});

it('tells the family about a new gathering, and the hosts who is coming', function () {
    $this->actingAs($this->me)->post(route('events.store'), ['kind' => 'gathering', 'title' => 'Sondagete', 'starts_on' => today()->addWeek()->toDateString(), 'share' => 'family']);

    expect(sentTo($this->them))->toHaveCount(1)
        ->and(sentTo($this->them)[0]['title'])->toBe('Nuwe byeenkoms: Sondagete')
        ->and(sentTo($this->me))->toBeEmpty();

    $event = Event::query()->sole();
    $this->actingAs($this->them)->post(route('gatherings.respond', $event), ['status' => 'yes', 'adults' => 2, 'children' => 1]);

    $mine = sentTo($this->me);
    expect($mine)->toHaveCount(1)
        ->and($mine[0]['body'])->toBe('Ander kom (3)')
        ->and($mine[0]['url'])->toBe(route('events.show', $event));
});

it('tells everyone about a date poll and the driver about a lift', function () {
    $this->actingAs($this->me)->post(route('polls.store'), ['title' => 'Kersfees', 'dates' => [today()->addDays(10)->toDateString(), today()->addDays(17)->toDateString()]]);
    expect(sentTo($this->them))->toHaveCount(1)->and(sentTo($this->them)[0]['title'])->toBe('Watter datums werk? Kersfees');

    $child = child($this->ours, ['name' => 'Anri']);
    $ballet = Event::query()->create(['title' => 'Ballet', 'starts_on' => today()->addDay(), 'household_id' => $this->ours->id, 'member_id' => $child->id]);
    $date = today()->addDay()->toDateString();

    Push::$sent = [];
    $this->actingAs($this->me)->post(route('lifts.store', $ballet), ['lifts' => [$date => ['there' => $this->me->id, 'back' => $this->them->id]]]);
    expect(sentTo($this->me))->toBeEmpty()
        ->and(sentTo($this->them))->toHaveCount(1)
        ->and(sentTo($this->them)[0]['title'])->toBe('Jy ry: Ballet (Anri)');

    // Saving again without a change says nothing more.
    $this->actingAs($this->me)->post(route('lifts.store', $ballet), ['lifts' => [$date => ['there' => $this->me->id, 'back' => $this->them->id]]]);
    expect(sentTo($this->them))->toHaveCount(1);
});

it('reminds people of tomorrow and of lifts nobody has taken', function () {
    PushSubscription::query()->create(['member_id' => $this->me->id, 'endpoint' => 'https://push.example.com/1', 'p256dh' => 'p', 'auth' => 'a']);
    $child = child($this->ours, ['name' => 'Anri']);
    Event::query()->create(['title' => 'Ballet', 'starts_on' => today()->addDay(), 'start_time' => '15:00', 'household_id' => $this->ours->id, 'member_id' => $child->id]);
    Event::query()->create(['title' => 'Dokter', 'starts_on' => today()->addDay(), 'start_time' => '09:15', 'household_id' => $this->ours->id]);
    Event::query()->create(['title' => 'Later', 'starts_on' => today()->addDays(5)]);

    $this->artisan('tribe:remind')->assertSuccessful();

    $mine = sentTo($this->me);
    expect($mine)->toHaveCount(2)
        ->and($mine[0]['body'])->toBe("09:15 Dokter\n15:00 Ballet (Anri)")
        ->and($mine[1]['title'])->toBe('Niemand ry nog nie')
        ->and(sentTo($this->them))->toBeEmpty();
});
