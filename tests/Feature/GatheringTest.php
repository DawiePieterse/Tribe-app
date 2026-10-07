<?php

use App\Models\Event;
use App\Models\GatheringResponse;

beforeEach(function () {
    $this->host = household('Oupa en Ouma');
    $this->ours = household('Ons', '#C2185B');
    $this->me = adult($this->ours);
    $this->gathering = Event::query()->create([
        'kind' => Event::GATHERING, 'title' => 'Sondagete', 'starts_on' => today()->addDays(3), 'host_household_id' => $this->host->id,
    ]);
});

it('asks households that have not answered, on the home screen', function () {
    $this->actingAs($this->me)->get(route('home'))->assertSee('Laat weet of julle kom');
});

it('records one answer per household, and replaces it', function () {
    $this->actingAs($this->me);
    $this->post(route('gatherings.respond', $this->gathering), ['status' => 'yes', 'adults' => 2, 'children' => 2])->assertRedirect();
    $this->post(route('gatherings.respond', $this->gathering), ['status' => 'no', 'adults' => 2, 'children' => 2])->assertRedirect();

    $response = GatheringResponse::query()->sole();
    expect($response->household_id)->toBe($this->ours->id)
        ->and($response->status)->toBe('no')
        ->and($response->adults)->toBe(0);

    $this->get(route('home'))->assertDontSee('Laat weet of julle kom');
});

it('lets a household claim an open item but not take another’s', function () {
    $this->actingAs($this->me);
    $this->post(route('gatherings.items.store', $this->gathering), ['title' => 'Slaai']);
    $item = $this->gathering->items()->sole();

    $this->post(route('gatherings.items.claim', $item));
    expect($item->fresh()->household_id)->toBe($this->ours->id);

    $this->actingAs(adult($this->host))->post(route('gatherings.items.claim', $item));
    expect($item->fresh()->household_id)->toBe($this->ours->id);
});

it('makes a new gathering whole-family even if "net ons huis" is sent', function () {
    $this->actingAs($this->me)->post(route('events.store'), [
        'kind' => 'gathering', 'title' => 'Braai', 'starts_on' => today()->toDateString(), 'share' => 'household',
    ]);

    $braai = Event::query()->where('title', 'Braai')->sole();
    expect($braai->household_id)->toBeNull()->and($braai->host_household_id)->toBe($this->ours->id);
});
