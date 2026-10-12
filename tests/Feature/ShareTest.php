<?php

use App\Models\Event;
use App\Models\InboxItem;
use App\Services\Messages\MessageReader;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->me = adult(household('Ons'));
    config(['services.anthropic.key' => null]);
});

it('reads a pasted message into a filled-in event form', function () {
    $response = $this->actingAs($this->me)->post(route('share.read'), ['text' => 'Braai by ons Saterdag om 6nm']);

    $response->assertRedirectContains('soort=gathering')->assertRedirectContains('tyd=18%3A00');
    $this->get($response->headers->get('Location'))->assertOk()->assertSee('Tribe het die boodskap gelees');
});

it('asks Claude when a key is set, and uses its answer', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [[
        'type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'calendar_entry',
        'input' => ['title' => 'Sondagete by Ouma-hulle', 'kind' => 'gathering', 'starts_on' => '2026-10-11', 'start_time' => '12:30', 'location' => null],
    ]], 'stop_reason' => 'tool_use'])]);

    $s = app(MessageReader::class)->read('Ouma nooi almal Sondag vir ete');

    expect($s->toArray())->toMatchArray(['title' => 'Sondagete by Ouma-hulle', 'starts_on' => '2026-10-11', 'start_time' => '12:30', 'source' => 'ai']);
    Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'test-key')
        && $request['tool_choice']['name'] === 'calendar_entry'
        && str_contains($request['system'], 'Ons'));
});

it('falls back to the built-in reader when Claude fails', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response('overloaded', 529)]);

    expect(app(MessageReader::class)->read('Braai more')->source)->toBe('simple');
});

it('ignores nonsense dates from Claude', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [[
        'type' => 'tool_use', 'name' => 'calendar_entry', 'input' => ['title' => 'X', 'kind' => 'party', 'starts_on' => '2026-02-30', 'start_time' => '25:00'],
    ]]])]);

    expect(app(MessageReader::class)->read('x')->toArray())->toMatchArray(['kind' => 'event', 'starts_on' => null, 'start_time' => null]);
});

it('takes messages from the Shortcut only with a valid key', function () {
    $this->post(route('share.receive'), ['text' => 'Braai more'])->assertUnauthorized();
    $this->withToken(str_repeat('x', 40))->post(route('share.receive'), ['text' => 'Braai more'])->assertUnauthorized();

    $this->actingAs($this->me)->post(route('share.key'))->assertRedirect(route('share.setup'));
    $key = session('share_key');
    auth()->logout();

    $this->withToken($key)->post(route('share.receive'), ['text' => 'Braai more om 6nm'])
        ->assertOk()
        ->assertSee('In Tribe: Braai more om 6nm');

    expect(InboxItem::query()->sole()->member_id)->toBe($this->me->id);
});

it('shows shared messages on the home screen until they are saved', function () {
    $item = InboxItem::query()->create(['member_id' => $this->me->id, 'text' => 'Braai Saterdag', 'suggestion' => ['title' => 'Braai', 'kind' => 'gathering', 'starts_on' => '2026-10-10']]);

    $this->actingAs($this->me)->get(route('home'))->assertSee('Gestuur na Tribe')->assertSee('Braai');
    $this->get(route('inbox.open', $item))->assertRedirectContains('inkassie='.$item->id)->assertRedirectContains('datum=2026-10-10');

    $this->post(route('events.store'), ['kind' => 'gathering', 'title' => 'Braai', 'starts_on' => '2026-10-10', 'share' => 'family', 'inbox_id' => $item->id]);

    expect($item->fresh()->handled_at)->not->toBeNull()
        ->and(Event::query()->count())->toBe(1);
    $this->get(route('home'))->assertDontSee('Gestuur na Tribe');
});

it('keeps shared messages private to the person who sent them', function () {
    $item = InboxItem::query()->create(['member_id' => $this->me->id, 'text' => 'Geheim', 'suggestion' => []]);
    $other = adult($this->me->household);

    $this->actingAs($other)->get(route('inbox.open', $item))->assertNotFound();
    $this->actingAs($other)->delete(route('inbox.discard', $item))->assertNotFound();
});

it('shows the setup and paste pages', function (string $route) {
    $this->actingAs($this->me)->get(route($route))->assertOk();
})->with(['share.setup', 'share.paste', 'feeds.index', 'polls.index', 'polls.create']);
