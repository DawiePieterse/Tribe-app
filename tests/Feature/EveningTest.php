<?php

use App\Mail\EveningSummaryMail;
use App\Models\Event;
use App\Models\Member;
use App\Models\PushSubscription;
use App\Services\Push;
use App\Services\Weather;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Push::fake();
    Mail::fake();
    $this->ours = household('Ons');
    $this->parent = adult($this->ours);
    $this->child = child($this->ours, ['name' => 'Anri']);
    $this->nanny = Member::query()->create(['household_id' => $this->ours->id, 'name' => 'Thandi', 'kind' => Member::HELPER, 'email' => 'thandi@example.com', 'evening_email' => true]);
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

    $mine = array_values(array_filter(Push::$sent, fn ($n) => $n['member_id'] === $this->parent->id));
    expect($mine)->toHaveCount(1)
        ->and($mine[0]['body'])->toBe("09:15 Dokter\n15:00–16:00 Ballet (Anri) · Dansstudio\nJy neem Anri om 15:00 · Dansstudio");
});

it('adds the weather with its flags when the town is set', function () {
    config(['services.weather.lat' => '-33.1', 'services.weather.lon' => '18.03']);
    Http::fake(['api.open-meteo.com/*' => Http::response(['daily' => [
        'temperature_2m_min' => [9.4], 'temperature_2m_max' => [31.2], 'precipitation_probability_max' => [65], 'weathercode' => [61], 'windspeed_10m_max' => [22.6],
    ]])]);

    $w = app(Weather::class)->on(today()->addDay());
    expect($w['min'])->toBe(9)->and($w['max'])->toBe(31)->and($w['condition'])->toBe('reën')
        ->and($w['flags'])->toBe(['Baie warm: waterbottel en hoed', 'Koud: trek warm aan', 'Reën moontlik: reënjas']);

    $this->actingAs($this->parent)->get(route('home'))->assertSee('9–31 °C · reën · reën 65%')->assertSee('Reën moontlik: reënjas');

    $this->artisan('tribe:remind')->assertSuccessful();
    Mail::assertSent(EveningSummaryMail::class, fn (EveningSummaryMail $m) => str_contains($m->render(), '9–31 °C'));
    // Today for Tuis, tomorrow for the summary; a second look at the same day comes from the cache.
    $this->actingAs($this->parent)->get(route('home'));
    Http::assertSentCount(2);
});

it('leaves the weather out when the town is not set, and turns the email on under settings', function () {
    expect(app(Weather::class)->on(today()))->toBeNull();

    $this->actingAs($this->parent)->put(route('settings.update'), ['name' => $this->parent->name, 'email' => $this->parent->email, 'evening_email' => 1])->assertRedirect();
    expect($this->parent->fresh()->evening_email)->toBeTrue();
});
