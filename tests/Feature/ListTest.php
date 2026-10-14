<?php

use App\Models\Event;
use App\Models\TodoList;

beforeEach(function () {
    $this->me = adult(household());
    $this->list = TodoList::query()->create(['household_id' => $this->me->household_id, 'name' => 'Inkopies']);
});

it('adds an item by JSON, as the offline script does', function () {
    $this->actingAs($this->me)
        ->postJson(route('items.store', $this->list), ['title' => 'Melk', 'assigned_member_id' => ''])
        ->assertCreated()
        ->assertJson(['title' => 'Melk']);
});

it('sets done to the value sent, so a replayed tap does not flip it back', function () {
    $item = $this->list->items()->create(['title' => 'Brood']);
    $this->actingAs($this->me);

    $this->postJson(route('items.toggle', $item), ['done' => true])->assertJson(['done' => true]);
    $this->postJson(route('items.toggle', $item), ['done' => true])->assertJson(['done' => true]);
    expect($item->fresh()->done)->toBeTrue();
});

it('clears ticked items', function () {
    $this->list->items()->create(['title' => 'Klaar', 'done' => true]);
    $this->list->items()->create(['title' => 'Oop']);

    $this->actingAs($this->me)->post(route('lists.clear', $this->list));
    expect($this->list->items()->pluck('title')->all())->toBe(['Oop']);
});

it('shows every main page', function (string $route) {
    $this->actingAs($this->me)->get(route($route))->assertOk();
})->with(['home', 'calendar', 'lists.index', 'grandchildren.index', 'contacts.index', 'more', 'settings', 'events.create']);

it('offers a Checkers search on open shopping-list items only', function () {
    $this->list->items()->create(['title' => '1 l melk']);
    $this->list->items()->create(['title' => 'Brood', 'done' => true]);
    $tasks = TodoList::query()->create(['household_id' => $this->me->household_id, 'name' => 'Take', 'kind' => TodoList::TASKS]);
    $tasks->items()->create(['title' => 'Sny die gras']);

    $this->actingAs($this->me)->get(route('lists.show', $this->list))
        ->assertSee('https://www.checkers.co.za/search?Search=milk', false)
        ->assertDontSee('Search=bread', false);

    $this->actingAs($this->me)->get(route('lists.show', $tasks))->assertDontSee('checkers.co.za', false);
});

it('shows only the coming week on the home screen', function () {
    Event::query()->create(['title' => 'Oor drie dae', 'starts_on' => today()->addDays(3), 'household_id' => $this->me->household_id]);
    Event::query()->create(['title' => 'Oor tien dae', 'starts_on' => today()->addDays(10), 'household_id' => $this->me->household_id]);

    $this->actingAs($this->me)->get(route('home'))
        ->assertSee('Oor drie dae')
        ->assertDontSee('Oor tien dae');
});

it('opens the calendar on the week, and on the month when asked', function () {
    $this->actingAs($this->me)->get(route('calendar'))->assertOk()->assertSee('class="week-strip"', false);
    $this->actingAs($this->me)->get(route('calendar', ['maand' => today()->format('Y-m')]))->assertOk()->assertDontSee('class="week-strip"', false);
});

it('fades what is already over on the calendar', function () {
    $this->travelTo(today()->setTime(12, 0));
    Event::query()->create(['title' => 'Gister', 'starts_on' => today()->subDay(), 'household_id' => $this->me->household_id]);
    Event::query()->create(['title' => 'Vanoggend', 'starts_on' => today(), 'start_time' => '08:00', 'end_time' => '09:00', 'household_id' => $this->me->household_id]);
    Event::query()->create(['title' => 'Vanmiddag', 'starts_on' => today(), 'start_time' => '15:00', 'household_id' => $this->me->household_id]);

    $html = $this->actingAs($this->me)->get(route('calendar', ['maand' => today()->format('Y-m')]))->getContent();

    expect($html)->toMatch('/class="entry is-over"[^>]*>\s*<strong>\s*Vanoggend/')
        ->and($html)->not->toMatch('/class="entry is-over"[^>]*>\s*<strong>\s*Vanmiddag/');
    if (today()->day > 1) {
        expect($html)->toMatch('/class="entry is-over"[^>]*>\s*<strong>\s*Gister/');
    }
});
