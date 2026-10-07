<?php

use App\Models\Contact;
use App\Models\Event;
use App\Models\TodoList;

beforeEach(function () {
    $this->ours = household('Ons');
    $this->theirs = household('Hulle', '#C2185B');
    $this->me = adult($this->ours);
    $this->other = adult($this->theirs);
});

it('hides another household’s private event, and shows family events', function () {
    $private = Event::query()->create(['title' => 'Hulle s’n', 'starts_on' => today()->addDay(), 'household_id' => $this->theirs->id]);
    $family = Event::query()->create(['title' => 'Almal s’n', 'starts_on' => today()->addDay()]);

    $this->actingAs($this->me);
    $this->get(route('home'))->assertSee('Almal s’n')->assertDontSee('Hulle s’n');
    $this->get(route('calendar'))->assertDontSee('Hulle s’n');
    $this->get(route('events.show', $private))->assertNotFound();
    $this->get(route('events.edit', $private))->assertNotFound();
    $this->delete(route('events.destroy', $private))->assertNotFound();
    $this->get(route('events.show', $family))->assertOk();

    expect(Event::query()->whereKey($private->id)->exists())->toBeTrue();
});

it('hides another household’s lists and items, also from admins', function () {
    $this->me->update(['is_admin' => true]);
    $list = TodoList::query()->create(['household_id' => $this->theirs->id, 'name' => 'Hulle inkopies']);
    $item = $list->items()->create(['title' => 'Melk']);

    $this->actingAs($this->me);
    $this->get(route('lists.index'))->assertDontSee('Hulle inkopies');
    $this->get(route('lists.show', $list))->assertNotFound();
    $this->post(route('items.store', $list), ['title' => 'Brood'])->assertNotFound();
    $this->postJson(route('items.toggle', $item), ['done' => true])->assertNotFound();
    $this->delete(route('items.destroy', $item))->assertNotFound();

    expect($item->fresh()->done)->toBeFalse();
});

it('hides another household’s contacts', function () {
    $contact = Contact::query()->create(['name' => 'Hulle dokter', 'category' => 'doctor', 'household_id' => $this->theirs->id]);

    $this->actingAs($this->me);
    $this->get(route('contacts.index'))->assertDontSee('Hulle dokter');
    $this->get(route('contacts.edit', $contact))->assertNotFound();
});

it('saves "net ons huis" against the creator’s household', function () {
    $this->actingAs($this->me)->post(route('events.store'), [
        'kind' => 'event', 'title' => 'Tandarts', 'starts_on' => today()->toDateString(), 'share' => 'household',
    ])->assertRedirect();

    expect(Event::query()->where('title', 'Tandarts')->value('household_id'))->toBe($this->ours->id);
});

it('keeps the family admin page for admins', function () {
    $this->actingAs($this->me)->get(route('family.index'))->assertForbidden();
    $this->me->update(['is_admin' => true]);
    $this->actingAs($this->me)->get(route('family.index'))->assertOk();
});

it('lets only the child’s own household or an admin edit a profile', function () {
    $kid = child($this->theirs);

    $this->actingAs($this->me)->get(route('grandchildren.show', $kid))->assertOk();
    $this->actingAs($this->me)->get(route('grandchildren.edit', $kid))->assertForbidden();
    $this->actingAs($this->other)->put(route('grandchildren.update', $kid), ['shoe_size' => '30'])->assertRedirect();

    expect($kid->fresh()->shoe_size)->toBe('30');
});
