<?php

namespace App\Http\Controllers;

use App\Models\DatePoll;
use App\Models\Event;
use App\Models\InboxItem;
use App\Models\TodoList;
use App\Services\Agenda;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Agenda $agenda): View
    {
        $me = $this->me();
        $today = CarbonImmutable::today();

        $upcoming = $agenda->between($me, $today, $today->addDays(42));

        // Gatherings coming up that this household has not answered yet.
        $toAnswer = Event::query()
            ->with('hostHousehold')
            ->where('kind', Event::GATHERING)
            ->visibleTo($me)
            ->where('starts_on', '>=', $today->toDateString())
            ->whereDoesntHave('responses', fn ($q) => $q->where('household_id', $me->household_id))
            ->orderBy('starts_on')
            ->get();

        // Date polls with a date this household hasn't marked yet.
        $polls = DatePoll::query()->whereNull('closed_at')->with('options.votes')->latest()->get()
            ->filter(fn (DatePoll $poll) => $poll->options->contains(
                fn ($option) => ! $option->votes->contains('household_id', $me->household_id)
            ))->values();

        $shopping = TodoList::query()
            ->where('household_id', $me->household_id)
            ->where('kind', TodoList::SHOPPING)
            ->withCount(['items as open_count' => fn ($q) => $q->where('done', false)])
            ->orderBy('id')
            ->first();

        return view('home', [
            'me' => $me,
            'today' => $today,
            'days' => $upcoming->groupBy(fn ($o) => $o->date->toDateString()),
            'birthdaysSoon' => $upcoming->filter(fn ($o) => $o->isBirthday() && $o->date->lessThanOrEqualTo($today->addDays(7))),
            'toAnswer' => $toAnswer,
            'polls' => $polls,
            'inbox' => InboxItem::query()->where('member_id', $me->id)->whereNull('handled_at')->latest()->get(),
            'shopping' => $shopping,
        ]);
    }
}
