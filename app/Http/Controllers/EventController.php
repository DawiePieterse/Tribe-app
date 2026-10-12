<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\GatheringResponse;
use App\Models\Household;
use App\Models\InboxItem;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    public function create(Request $request): View
    {
        $date = $request->date('datum') ?? CarbonImmutable::today();
        $kind = in_array($request->query('soort'), Event::KINDS, true) ? (string) $request->query('soort') : Event::EVENT;

        $event = new Event([
            'kind' => $kind,
            'title' => mb_substr((string) $request->query('titel', ''), 0, 255) ?: null,
            'start_time' => $this->time($request->query('tyd')),
            'end_time' => $this->time($request->query('eindtyd')),
            'ends_on' => $request->date('tot'),
            'location' => mb_substr((string) $request->query('waar', ''), 0, 255) ?: null,
            'notes' => mb_substr((string) $request->query('notas', ''), 0, 5000) ?: null,
            'starts_on' => $date,
            'household_id' => $kind === Event::EVENT ? $this->me()->household_id : null,
            'host_household_id' => $kind === Event::GATHERING ? $this->me()->household_id : null,
            'repeats' => $kind === Event::ANNIVERSARY ? Event::YEARLY : Event::ONCE,
        ]);

        return view('events.form', $this->formData($event) + [
            'pollId' => $request->integer('peiling') ?: null,
            'inboxId' => $request->integer('inkassie') ?: null,
            'fromMessage' => $request->boolean('gelees'),
        ]);
    }

    private function time(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : null;
    }

    public function store(Request $request): RedirectResponse
    {
        $event = Event::query()->create($this->validated($request) + ['created_by' => $this->me()->id]);

        if ($request->integer('poll_id') > 0) {
            DatePollController::close($request->integer('poll_id'), $event->id);
        }

        // A message shared with "Stuur na Tribe" is done once it is on the calendar.
        if ($request->integer('inbox_id') > 0) {
            InboxItem::query()->whereKey($request->integer('inbox_id'))->where('member_id', $this->me()->id)
                ->update(['handled_at' => now()]);
        }

        return redirect()->route('events.show', $event)->with('status', __('Bygevoeg.'));
    }

    public function show(Event $event): View
    {
        $this->ensureVisible($event);
        $me = $this->me();
        $event->load(['member.household', 'household', 'hostHousehold', 'feed', 'responses.household', 'items.household']);

        $responses = $event->responses->keyBy('household_id');

        return view('events.show', [
            'event' => $event,
            'me' => $me,
            'households' => Household::query()->orderBy('id')->get(),
            'responses' => $responses,
            'mine' => $responses->get($me->household_id),
            'coming' => $event->responses->where('status', GatheringResponse::YES),
            'allergies' => $event->isGathering()
                ? Member::query()->children()->whereNotNull('allergies')->where('allergies', '!=', '')->get()
                : collect(),
        ]);
    }

    public function edit(Event $event): View
    {
        $this->ensureEditable($event);

        return view('events.form', $this->formData($event));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->ensureEditable($event);
        $event->update($this->validated($request));

        return redirect()->route('events.show', $event)->with('status', __('Gestoor.'));
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->ensureEditable($event);
        $month = $event->starts_on->format('Y-m');
        $event->delete();

        return redirect()->route('calendar', ['maand' => $month])->with('status', __('Verwyder.'));
    }

    /** Entries from a subscribed calendar change at the source, not here. */
    private function ensureEditable(Event $event): void
    {
        $this->ensureVisible($event);
        abort_if($event->isFromFeed(), 403, __('Hierdie inskrywing kom uit ’n ingetekende kalender en kan nie hier verander word nie.'));
    }

    /** @return array<string, mixed> */
    private function formData(Event $event): array
    {
        return [
            'event' => $event,
            'me' => $this->me(),
            'households' => Household::query()->orderBy('id')->get(),
            'members' => Member::query()->with('household')->orderBy('household_id')->orderBy('name')->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $me = $this->me();

        $data = $request->validate([
            'kind' => ['required', Rule::in(Event::KINDS)],
            'title' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'member_id' => ['nullable', Rule::exists('members', 'id')],
            'host_household_id' => ['nullable', Rule::exists('households', 'id')],
            'share' => ['required', Rule::in(['household', 'family'])],
            'repeats' => ['nullable', Rule::in(Event::REPEATS)],
            'repeat_until' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        // Anniversaries always come round yearly; nothing else repeats unless asked.
        $repeats = $data['kind'] === Event::ANNIVERSARY ? Event::YEARLY : ($data['repeats'] ?? Event::ONCE);

        $gathering = $data['kind'] === Event::GATHERING;

        return [
            'kind' => $data['kind'],
            'title' => $data['title'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'member_id' => $data['member_id'] ?? null,
            // Gatherings are for the whole family; anything else follows the "who sees it" choice.
            'household_id' => $gathering || $data['share'] === 'family' ? null : $me->household_id,
            'host_household_id' => $gathering ? ($data['host_household_id'] ?? $me->household_id) : null,
            'repeats' => $repeats,
            'repeat_until' => $repeats === Event::ONCE ? null : ($data['repeat_until'] ?? null),
        ];
    }
}
