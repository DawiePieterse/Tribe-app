<?php

namespace App\Http\Controllers;

use App\Models\DatePoll;
use App\Models\DatePollOption;
use App\Models\DatePollVote;
use App\Models\Household;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** "Vind ’n datum": a few possible dates, each household marks which suit them. */
class DatePollController extends Controller
{
    public function index(): View
    {
        return view('polls.index', [
            'open' => DatePoll::query()->whereNull('closed_at')->with('options')->latest()->get(),
            'closed' => DatePoll::query()->whereNotNull('closed_at')->with('event')->latest('closed_at')->limit(10)->get(),
        ]);
    }

    public function create(): View
    {
        return view('polls.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'dates' => ['required', 'array', 'max:12'],
            'dates.*' => ['nullable', 'date', 'after_or_equal:today'],
            'times' => ['nullable', 'array'],
            'times.*' => ['nullable', 'date_format:H:i'],
        ]);

        $dates = array_map(fn (mixed $d): ?string => is_string($d) && $d !== '' ? $d : null, array_values((array) $data['dates']));
        $times = array_map(fn (mixed $t): ?string => is_string($t) && $t !== '' ? $t : null, array_values((array) ($data['times'] ?? [])));

        $options = collect($dates)
            ->map(fn (?string $date, int $i) => $date !== null ? ['date' => $date, 'time' => $times[$i] ?? null] : null)
            ->filter()
            ->unique(fn ($o) => $o['date'].' '.$o['time'])
            ->values();

        if ($options->count() < 2) {
            return back()->withInput()->withErrors(['dates' => __('Kies ten minste twee datums.')]);
        }

        $poll = DB::transaction(function () use ($data, $options) {
            $poll = DatePoll::query()->create(['title' => $data['title'], 'notes' => $data['notes'] ?? null, 'created_by' => $this->me()->id]);
            foreach ($options as $option) {
                $poll->options()->create($option);
            }

            return $poll;
        });

        return redirect()->route('polls.show', $poll)->with('status', __('Klaar. Merk nou wat vir julle huis werk.'));
    }

    public function show(DatePoll $poll): View
    {
        $poll->load(['options.votes', 'creator', 'event']);
        $me = $this->me();
        $households = Household::query()->orderBy('id')->get();

        $best = $poll->options->sortByDesc(fn (DatePollOption $o) => $o->score())->first();

        return view('polls.show', [
            'poll' => $poll,
            'me' => $me,
            'households' => $households,
            'mine' => $poll->options->mapWithKeys(fn (DatePollOption $o) => [
                $o->id => $o->votes->firstWhere('household_id', $me->household_id)?->answer,
            ]),
            'bestId' => $best !== null && $best->score() > 0 ? $best->id : null,
            'everyone' => $poll->options->filter(fn (DatePollOption $o) => $households->every(
                fn (Household $h) => $o->votes->contains(fn (DatePollVote $v) => $v->household_id === $h->id && $v->answer === DatePollVote::YES)
            ))->pluck('id')->all(),
            'canManage' => $me->is_admin || $poll->getAttribute('created_by') === $me->id,
        ]);
    }

    /** One household's answers for every date at once; answering again replaces them. */
    public function vote(Request $request, DatePoll $poll): RedirectResponse
    {
        abort_unless($poll->isOpen(), 422, __('Hierdie datum is reeds gekies.'));
        $me = $this->me();

        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['required', Rule::in([DatePollVote::YES, DatePollVote::MAYBE, DatePollVote::NO])],
        ]);

        $optionIds = $poll->options()->pluck('id')->all();

        foreach ($data['answers'] as $optionId => $answer) {
            if (! in_array((int) $optionId, $optionIds, true)) {
                continue;
            }
            DatePollVote::query()->updateOrCreate(
                ['date_poll_option_id' => (int) $optionId, 'household_id' => $me->household_id],
                ['answer' => $answer, 'voted_by' => $me->id],
            );
        }

        return redirect()->route('polls.show', $poll)->with('status', __('Dankie, julle keuses is gestoor.'));
    }

    /** Opens a new gathering filled in with the chosen date; saving it closes the poll. */
    public function choose(DatePollOption $option): RedirectResponse
    {
        $poll = $option->poll;
        abort_unless($poll->isOpen(), 422, __('Hierdie datum is reeds gekies.'));

        return redirect()->route('events.create', array_filter([
            'soort' => 'gathering',
            'datum' => $option->date->toDateString(),
            'tyd' => $option->time ? substr($option->time, 0, 5) : null,
            'titel' => $poll->title,
            'peiling' => $poll->id,
        ]));
    }

    public function destroy(DatePoll $poll): RedirectResponse
    {
        $me = $this->me();
        abort_unless($me->is_admin || $poll->getAttribute('created_by') === $me->id, 403);
        $poll->delete();

        return redirect()->route('polls.index')->with('status', __('Verwyder.'));
    }

    /** Called by EventController when a gathering made from a poll is saved. */
    public static function close(int $pollId, int $eventId): void
    {
        DatePoll::query()->whereKey($pollId)->whereNull('closed_at')->update(['closed_at' => CarbonImmutable::now(), 'event_id' => $eventId]);
    }
}
