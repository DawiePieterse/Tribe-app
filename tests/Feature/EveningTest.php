<?php

use App\Mail\EveningSummaryMail;
use App\Models\Event;
use App\Models\PushSubscription;
use App\Services\Push;
use App\Services\Weather;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->push = Push::fake();
    Mail::fake();
    $this->ours = household('Ons');
    $this->parent = adult($this->ours);
    $this->child = child($this->ours, ['name' => 'Anri']);
    $this->nanny = helper($this->ours, ['evening_email' => true]);
});

it('emails the nanny tomorrow’s program with her lifts, and pushes it to the parent', function () {
    PushSubscription::query()->create(['member_id' => $this->parent->id, 'endpoint' => 'https://push.example.com/1', 'p256dh' => 'p', 'auth' => 'a']);
    Event::query()->create(['title' => 'Ballet', 'starts_on' => today()->addDay(), 'start_time' => '15:00', 'end_time' => '16:00', 'location' => 'Dansstudio', 'household_id' => $this->ours->id, 'member_id' => $this->child->id, 'there_member_id' => $this->parent->id, 'back_member_id' => $this->nanny->id]);
    Event::query()->create(['title' => 'Dokter', 'starts_on' => today()->addDay(), 'start_time' => '09:15', 'household_id' => $this->ours->id]);

    $this->artisan('tribe:remind')->assertSuccessful();

    Mail::assertSent(EveningSummaryMail::class, function (EveningSummaryMail $mail) {
        $text = $mail->render();

        return $mail->hasTo('thandi@example.com')
            && str_contains($text, '15:00–16:00 Ballet (Anri) · Dansstudio')
            && str_contains($text, 'Jy haal Anri om 16:00 · Dansstudio')
            && ! str_contains($text, 'Jy neem');
    });
    Mail::assertNotSent(EveningSummaryMail::class, fn (EveningSummaryMail $mail) => $mail->hasTo($this->parent->email));

    $mine = $this->push->sentTo($this->parent);
    expect($mine)->toHaveCount(1)
        ->and($mine[0]['body'])->toBe("09:15 Dokter\n15:00–16:00 Ballet (Anri) · Dansstudio\nJy neem Anri om 15:00 · Dansstudio");
});

it('adds the weather with its flags when the town is set', function () {
    $this->ours->update(['town' => 'Langebaan', 'lat' => '-33.09000', 'lon' => '18.03000']);
    $days = [today()->toDateString(), today()->addDay()->toDateString(), today()->addDays(2)->toDateString()];
    Http::fake(['api.open-meteo.com/*' => Http::response(['daily' => [
        'time' => $days, 'temperature_2m_min' => [9.4, 9.4, 12], 'temperature_2m_max' => [31.2, 31.2, 20], 'precipitation_probability_max' => [65, 65, 0], 'weathercode' => [61, 61, 0],
    ]])]);

    $w = app(Weather::class)->for($this->ours->fresh(), today()->addDay());
    expect($w['min'])->toBe(9)->and($w['max'])->toBe(31)->and($w['condition'])->toBe('reën')
        ->and($w['flags'])->toBe(['Baie warm: waterbottel en hoed', 'Koud: trek warm aan', 'Reën moontlik: reënjas']);

    $this->actingAs($this->parent)->get(route('home'))->assertSee('9–31 °C · reën · reën 65%')->assertSee('Reën moontlik: reënjas');

    $this->artisan('tribe:remind')->assertSuccessful();
    Mail::assertSent(EveningSummaryMail::class, fn (EveningSummaryMail $m) => str_contains($m->render(), '9–31 °C'));
    // One request for Tuis (today) and the summary (tomorrow); after that it comes from the cache.
    $this->actingAs($this->parent)->get(route('home'));
    Http::assertSentCount(1);
});

it('leaves the weather out when the town is not set, and turns the email on under settings', function () {
    expect(app(Weather::class)->for($this->ours, today()))->toBeNull();
    $this->actingAs($this->parent)->get(route('home'))->assertDontSee('°C');

    $this->actingAs($this->parent)->put(route('settings.update'), ['name' => $this->parent->name, 'email' => $this->parent->email, 'evening_email' => 1])->assertRedirect();
    expect($this->parent->fresh()->evening_email)->toBeTrue();
});

it('looks a household’s town up when an admin sets it', function () {
    $admin = adult($this->ours, ['is_admin' => true]);
    Http::fake([
        'geocoding-api.open-meteo.com/*' => fn ($request) => Http::response(['results' => str_contains($request->url(), 'Langebaan') ? [
            ['name' => 'Langebaan', 'country_code' => 'ZA', 'admin1' => 'Western Cape', 'latitude' => -33.0917, 'longitude' => 18.0333],
        ] : []]),
        'api.open-meteo.com/*' => Http::response(['daily' => ['time' => [today()->toDateString()], 'temperature_2m_min' => [12], 'temperature_2m_max' => [22], 'precipitation_probability_max' => [0], 'weathercode' => [0]]]),
    ]);

    $this->actingAs($admin)->put(route('family.households.update', $this->ours), ['name' => 'Ons', 'colour' => '#2E7D5B', 'town' => 'Langebaan'])->assertRedirect();
    $ours = $this->ours->fresh();
    expect($ours->town)->toBe('Langebaan, Western Cape')->and($ours->lat)->toBe('-33.09170')->and($ours->lon)->toBe('18.03330');

    // Saving again with the same town doesn't look it up again; another household has its own weather.
    $this->actingAs($admin)->put(route('family.households.update', $ours), ['name' => 'Ons', 'colour' => '#2E7D5B', 'town' => $ours->town]);
    Http::assertSentCount(1);
    $this->actingAs($this->parent)->get(route('home'))->assertSee('12–22 °C');
    $other = household('Ander');
    $this->actingAs(adult($other))->get(route('home'))->assertDontSee('°C');

    // A town nobody can find is refused, and the old one kept.
    $this->actingAs($admin)->put(route('family.households.update', $ours), ['name' => 'Ons', 'colour' => '#2E7D5B', 'town' => 'Nêrensville'])->assertSessionHasErrors('town');
    expect($ours->fresh()->town)->toBe('Langebaan, Western Cape');
});

it('remembers a failed forecast briefly instead of asking on every page', function () {
    $this->ours->update(['town' => 'Langebaan', 'lat' => '-33.09000', 'lon' => '18.03000']);
    Http::fake(['api.open-meteo.com/*' => Http::response('', 503)]);

    $this->actingAs($this->parent)->get(route('home'))->assertOk()->assertDontSee('°C');
    $this->actingAs($this->parent)->get(route('home'))->assertOk();

    Http::assertSentCount(1);
});

it('names the town before the weather on the home page', function () {
    $this->ours->update(['town' => 'Langebaan, Western Cape', 'lat' => '-33.09000', 'lon' => '18.03000']);
    Http::fake(['api.open-meteo.com/*' => Http::response(['daily' => [
        'time' => [today()->toDateString()], 'temperature_2m_min' => [14], 'temperature_2m_max' => [15],
        'precipitation_probability_max' => [0], 'weathercode' => [3],
    ]])]);

    $this->actingAs(adult($this->ours))->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Langebaan:', '14–15 °C'], false)
        ->assertDontSee('Langebaan, Western Cape');
});
