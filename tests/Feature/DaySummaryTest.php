<?php

use App\Models\Event;
use App\Models\ListItem;
use App\Models\TodoList;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.anthropic.key' => null]);
    CarbonImmutable::setTestNow(today()->setTime(10, 0));
    $this->me = adult(household('Ons'));
    $this->list = TodoList::query()->create(['household_id' => $this->me->household_id, 'name' => 'Inkopies']);
    foreach (['melk', 'brood', 'eiers', 'botter', 'kaas'] as $title) {
        ListItem::query()->create(['list_id' => $this->list->id, 'title' => $title]);
    }
    Event::query()->create(['title' => 'Sakkedag', 'starts_on' => today(), 'start_time' => '08:00', 'end_time' => '09:00', 'household_id' => $this->me->household_id]);
    Event::query()->create(['title' => 'Tandarts', 'starts_on' => today(), 'start_time' => '15:00', 'household_id' => $this->me->household_id]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('sums up the day in Tribe’s own words without Claude', function () {
    $this->actingAs($this->me)->get(route('home'))
        ->assertOk()
        ->assertSee(__('Vandag in kort'))
        ->assertSee('Vandag: 15:00 Tandarts.')
        ->assertDontSee('08:00–09:00 Sakkedag')
        ->assertSee('Inkopies: melk, brood, eiers en nog 2.')
        ->assertDontSee('data-day-summary', false);
});

it('has Claude write it once, fetched by the page, and kept until something changes', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [
        ['type' => 'tool_use', 'name' => 'day_summary', 'input' => ['summary' => 'Net die tandarts om drie. Koop melk, brood en nog 3 dinge.']],
    ]])]);

    // First the plain version, with the address to fetch Claude's from.
    $this->actingAs($this->me)->get(route('home'))->assertSee('data-day-summary', false)->assertSee('Vandag: 15:00 Tandarts.');

    $this->getJson(route('home.summary'))->assertOk()->assertJson(['text' => 'Net die tandarts om drie. Koop melk, brood en nog 3 dinge.']);
    Http::assertSent(fn (Request $r) => $r['tool_choice']['name'] === 'day_summary'
        && str_contains($r['messages'][0]['content'], '15:00 Tandarts')
        && str_contains($r['messages'][0]['content'], '08:00–09:00 Sakkedag (verby)')
        && str_contains($r['messages'][0]['content'], 'melk, brood, eiers, botter, kaas'));

    // Kept: the page shows it straight away and Claude isn't asked again.
    $this->get(route('home'))->assertSee('Net die tandarts om drie.')->assertDontSee('data-day-summary', false);
    Http::assertSentCount(1);

    // Something bought: written again.
    ListItem::query()->where('title', 'melk')->update(['done' => true]);
    $this->get(route('home'))->assertSee('data-day-summary', false);
});

it('falls back to the plain summary when Claude fails, and waits before asking again', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response('error', 500)]);

    $this->actingAs($this->me)->getJson(route('home.summary'))->assertJson(['text' => 'Vandag: 15:00 Tandarts. Inkopies: melk, brood, eiers en nog 2.']);
    $this->get(route('home'))->assertDontSee('data-day-summary', false);
    Http::assertSentCount(1);
});

it('leaves the shopping list out for a helper', function () {
    $nanny = helper($this->me->household);

    $this->actingAs($nanny)->get(route('home'))->assertSee('Vandag: 15:00 Tandarts.')->assertDontSee('melk');
});
