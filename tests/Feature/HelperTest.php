<?php

use App\Models\Event;
use App\Models\Lift;
use App\Models\Member;
use App\Models\TodoList;

beforeEach(function () {
    $this->ours = household('Ons');
    $this->other = household('Ander', '#C2185B');
    $this->parent = adult($this->ours);
    $this->child = child($this->ours, ['name' => 'Anri']);
    $this->nanny = Member::query()->create(['household_id' => $this->ours->id, 'name' => 'Thandi', 'kind' => Member::HELPER, 'email' => 'thandi@example.com']);
});

it('lets a helper log in and see only their household’s days', function () {
    expect($this->nanny->canLogIn())->toBeTrue();

    $ballet = Event::query()->create(['title' => 'Ballet', 'starts_on' => today()->addDay(), 'household_id' => $this->ours->id, 'member_id' => $this->child->id]);
    Event::query()->create(['title' => 'Sondagete', 'starts_on' => today()->addDays(2)]);
    Event::query()->create(['title' => 'Hulle geheim', 'starts_on' => today()->addDays(3), 'household_id' => $this->other->id]);
    TodoList::query()->create(['name' => 'Inkopies', 'household_id' => $this->ours->id]);

    $this->actingAs($this->nanny);
    $this->get(route('home'))->assertOk()->assertSee('Ballet')->assertSee('Sondagete')->assertDontSee('Hulle geheim')->assertDontSee('Lyste');
    $this->get(route('calendar'))->assertOk()->assertSee('Ballet');
    $this->get(route('events.show', $ballet))->assertOk()->assertSee(__('Wie ry?'));
    $this->get(route('more'))->assertOk()->assertSee(__('My instellings'))->assertDontSee(__('Belangrike nommers'));

    // Only the calendar: everything else stays closed.
    foreach (['grandchildren.index', 'contacts.index', 'lists.index', 'recipes.index', 'meals.week', 'ideas.index', 'polls.index', 'feeds.index', 'share.setup', 'family.index'] as $route) {
        $this->get(route($route))->assertForbidden();
    }
});

it('lets a helper add their household’s appointments, never the family’s', function () {
    $this->actingAs($this->nanny);

    $this->post(route('events.store'), ['kind' => 'event', 'title' => 'Tandarts', 'starts_on' => today()->addDay()->toDateString(), 'share' => 'family', 'member_id' => $this->child->id])->assertRedirect();
    $event = Event::query()->sole();
    expect($event->household_id)->toBe($this->ours->id);

    $this->post(route('events.store'), ['kind' => 'gathering', 'title' => 'Braai', 'starts_on' => today()->toDateString(), 'share' => 'family'])->assertSessionHasErrors('kind');

    $family = Event::query()->create(['title' => 'Sondagete', 'starts_on' => today()->addDays(2)]);
    $this->get(route('events.edit', $family))->assertForbidden();
});

it('plans who takes and fetches a child, per day, and shows it on the agenda', function () {
    $ballet = Event::query()->create(['title' => 'Ballet', 'starts_on' => today()->addDay(), 'start_time' => '15:00', 'household_id' => $this->ours->id, 'member_id' => $this->child->id, 'repeats' => 'weekly']);
    $first = today()->addDay()->toDateString();
    $second = today()->addDays(8)->toDateString();

    $this->actingAs($this->parent)->get(route('events.show', $ballet))->assertSee(__('Wie ry?'))->assertSee('Thandi')->assertSee('name="lifts['.$first.'][there]"', false);

    $this->actingAs($this->nanny)->post(route('lifts.store', $ballet), ['lifts' => [
        $first => ['there' => $this->parent->id, 'back' => $this->nanny->id],
        $second => ['there' => $this->nanny->id, 'back' => '0'],
        '2030-01-01' => ['there' => $this->parent->id, 'back' => $this->parent->id],
    ]])->assertRedirect(route('events.show', $ballet).'#kere');

    expect(Lift::query()->count())->toBe(4)
        ->and(Lift::query()->where('date', $first)->where('direction', 'back')->value('member_id'))->toBe($this->nanny->id)
        ->and(Lift::query()->where('date', $second)->where('direction', 'back')->value('member_id'))->toBeNull();

    $page = $this->actingAs($this->parent)->get(route('home'))->assertSee(__('Wie ry hierdie week'));
    $page->assertSee(__('Neem: :there · Haal: :back', ['there' => $this->parent->firstName(), 'back' => 'Thandi']));
    $this->actingAs($this->parent)->get(route('calendar', ['maand' => today()->addDays(8)->format('Y-m')]))
        ->assertSee(__('Neem: :there · Haal: :back', ['there' => 'Thandi', 'back' => '?']));

    // Not for grown-ups' appointments.
    $dentist = Event::query()->create(['title' => 'Tandarts', 'starts_on' => today()->addDay(), 'household_id' => $this->ours->id, 'member_id' => $this->parent->id]);
    $this->actingAs($this->parent)->get(route('events.show', $dentist))->assertDontSee(__('Wie ry?'));
    $this->actingAs($this->parent)->post(route('lifts.store', $dentist), ['lifts' => []])->assertNotFound();
});

it('uses the usual drivers, lets one day differ, and lets one day fall away', function () {
    $ballet = Event::query()->create(['title' => 'Ballet', 'starts_on' => today()->addDay(), 'start_time' => '15:00', 'household_id' => $this->ours->id, 'member_id' => $this->child->id, 'repeats' => 'weekly', 'there_member_id' => $this->parent->id, 'back_member_id' => $this->nanny->id]);
    $first = today()->addDay()->toDateString();
    $second = today()->addDays(8);

    // Usual drivers show without any Lift rows.
    $this->actingAs($this->parent)->get(route('calendar', ['maand' => today()->addDay()->format('Y-m')]))->assertSee(__('Neem: :there · Haal: :back', ['there' => $this->parent->firstName(), 'back' => 'Thandi']));

    // Next week nobody fetches; the week after is as usual.
    $this->actingAs($this->parent)->post(route('lifts.store', $ballet), ['lifts' => [$second->toDateString() => ['there' => '', 'back' => '0'], $first => ['there' => '', 'back' => '']]])->assertRedirect();
    expect(Lift::query()->count())->toBe(1);
    $this->actingAs($this->parent)->get(route('calendar', ['maand' => $second->format('Y-m')]))->assertSee(__('Neem: :there · Haal: :back', ['there' => $this->parent->firstName(), 'back' => '?']));

    // This Tuesday's practice is cancelled.
    $this->actingAs($this->nanny)->post(route('events.skip', $ballet), ['date' => $first, 'skip' => 1])->assertRedirect();
    $this->actingAs($this->nanny)->get(route('home'))->assertDontSee('d'.$first, false);
    $this->actingAs($this->parent)->get(route('events.show', $ballet))->assertSee(__('Herstel'));
    $this->actingAs($this->parent)->post(route('events.skip', $ballet), ['date' => '2030-01-01', 'skip' => 1])->assertUnprocessable();
    $this->actingAs($this->parent)->post(route('events.skip', $ballet), ['date' => $first, 'skip' => 0]);
    $this->actingAs($this->nanny)->get(route('home'))->assertSee('d'.$first, false);

    // The iPhone feed leaves a skipped day out.
    $this->actingAs($this->parent)->post(route('events.skip', $ballet), ['date' => $first, 'skip' => 1]);
    $this->get(route('ics', ['token' => $this->parent->calendarToken()]))->assertSee('EXDATE:'.today()->addDay()->setTimeFromTimeString('15:00')->utc()->format('Ymd\THis\Z'));
});

it('offers places used before and a map link', function () {
    Event::query()->create(['title' => 'Rugby', 'starts_on' => today(), 'location' => 'Skool se rugbyveld']);
    $swim = Event::query()->create(['title' => 'Swem', 'starts_on' => today(), 'location' => 'Swembad']);

    $this->actingAs($this->parent)->get(route('events.create'))->assertSee('<option value="Skool se rugbyveld">', false);
    $this->actingAs($this->parent)->get(route('events.show', $swim))->assertSee('https://maps.apple.com/?q=Swembad');
});

it('shows the week view with a dot per colour', function () {
    Event::query()->create(['title' => 'Ballet', 'starts_on' => today(), 'household_id' => $this->ours->id, 'member_id' => $this->child->id]);

    $this->actingAs($this->parent)->get(route('calendar', ['aansig' => 'week']))
        ->assertOk()->assertSee(__('Hierdie week'))->assertSee('class="week-strip"', false)->assertSee('#d'.today()->toDateString())->assertSee('Ballet');
    $this->actingAs($this->parent)->get(route('calendar', ['aansig' => 'week', 'week' => today()->addWeeks(3)->toDateString()]))->assertSee(__('Niks hierdie week nie.'));
});
