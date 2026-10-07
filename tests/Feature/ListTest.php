<?php

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
