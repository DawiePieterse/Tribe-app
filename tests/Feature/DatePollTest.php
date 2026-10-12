<?php

use App\Models\DatePoll;
use App\Models\Event;

beforeEach(function () {
    $this->ours = household('Ons');
    $this->theirs = household('Hulle', '#C2185B');
    $this->me = adult($this->ours);
    $this->other = adult($this->theirs);
});

function makePoll($test): DatePoll
{
    $test->actingAs($test->me)->post(route('polls.store'), [
        'title' => 'Kersfees',
        'dates' => [today()->addDays(10)->toDateString(), today()->addDays(11)->toDateString(), '', today()->addDays(10)->toDateString()],
        'times' => ['12:00', '', '', '12:00'],
    ])->assertRedirect();

    return DatePoll::query()->with('options')->sole();
}

it('needs at least two different dates', function () {
    $this->actingAs($this->me)->post(route('polls.store'), [
        'title' => 'Braai', 'dates' => [today()->toDateString(), today()->toDateString()],
    ])->assertSessionHasErrors('dates');

    expect(DatePoll::query()->count())->toBe(0);
});

it('asks each household on the home screen until they answer', function () {
    $poll = makePoll($this);
    expect($poll->options)->toHaveCount(2);

    $this->actingAs($this->other)->get(route('home'))->assertSee('Watter datums werk vir julle?');

    $answers = $poll->options->mapWithKeys(fn ($o) => [$o->id => 'yes'])->all();
    $this->actingAs($this->other)->post(route('polls.vote', $poll), ['answers' => $answers])->assertRedirect();
    $this->actingAs($this->other)->get(route('home'))->assertDontSee('Watter datums werk vir julle?');
});

it('shows the date that suits everyone', function () {
    $poll = makePoll($this);
    [$first, $second] = $poll->options->all();

    $this->actingAs($this->me)->post(route('polls.vote', $poll), ['answers' => [$first->id => 'no', $second->id => 'yes']]);
    $this->actingAs($this->other)->post(route('polls.vote', $poll), ['answers' => [$first->id => 'yes', $second->id => 'yes']]);

    $this->actingAs($this->me)->get(route('polls.show', $poll))
        ->assertOk()
        ->assertSeeInOrder([$second->date->translatedFormat('l j F'), 'Pas almal']);
});

it('turns the chosen date into a gathering and closes the poll', function () {
    $poll = makePoll($this);
    $option = $poll->options->first();

    $this->actingAs($this->me)->post(route('polls.choose', $option))
        ->assertRedirectContains('soort=gathering')
        ->assertRedirectContains('peiling='.$poll->id);

    $this->actingAs($this->me)->post(route('events.store'), [
        'kind' => 'gathering', 'title' => 'Kersfees', 'starts_on' => $option->date->toDateString(),
        'start_time' => '12:00', 'share' => 'family', 'poll_id' => $poll->id,
    ])->assertRedirect();

    $poll->refresh();
    expect($poll->isOpen())->toBeFalse()
        ->and($poll->event_id)->toBe(Event::query()->sole()->id);

    $this->actingAs($this->other)->post(route('polls.vote', $poll), ['answers' => [$option->id => 'no']])->assertStatus(422);
});

it('lets only the person who asked, or an admin, delete it', function () {
    $poll = makePoll($this);

    $this->actingAs($this->other)->delete(route('polls.destroy', $poll))->assertForbidden();
    $this->actingAs($this->me)->delete(route('polls.destroy', $poll))->assertRedirect();
    expect(DatePoll::query()->count())->toBe(0);
});
