<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\GatheringResponse;
use App\Models\Household;
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
            'starts_on' => $date,
            'household_id' => $kind === Event::EVENT ? $this->me()->household_id : null,
            'host_household_id' => $kind === Event::GATHERING ? $this->me()->household_id : null,
            'yearly' => $kind === Event::ANNIVERSARY,
        ]);

        return view('events.form', $this->formData($event));
    }

    public function store(Request $request): RedirectResponse
    {
        $event = Event::query()->create($this->validated($request) + ['created_by' => $this->me()->id]);

        return redirect()->route('events.show', $event)->with('status', __('Bygevoeg.'));
    }

    public function show(Event $event): View
    {
        $this->ensureVisible($event);
        $me = $this->me();
        $event->load(['member.household', 'household', 'hostHousehold', 'responses.household', 'items.household']);

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
        $this->ensureVisible($event);

        return view('events.form', $this->formData($event));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->ensureVisible($event);
        $event->update($this->validated($request));

        return redirect()->route('events.show', $event)->with('status', __('Gestoor.'));
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->ensureVisible($event);
        $month = $event->starts_on->format('Y-m');
        $event->delete();

        return redirect()->route('calendar', ['maand' => $month])->with('status', __('Verwyder.'));
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
        ]);

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
            'yearly' => $data['kind'] === Event::ANNIVERSARY,
        ];
    }
}
