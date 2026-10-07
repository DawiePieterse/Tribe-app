<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\GatheringItem;
use App\Models\GatheringResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GatheringController extends Controller
{
    /** Each household answers once for everyone in it; answering again replaces the answer. */
    public function respond(Request $request, Event $event): RedirectResponse
    {
        $this->ensureGathering($event);
        $me = $this->me();

        $data = $request->validate([
            'status' => ['required', Rule::in([GatheringResponse::YES, GatheringResponse::MAYBE, GatheringResponse::NO])],
            'adults' => ['nullable', 'integer', 'min:0', 'max:30'],
            'children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $coming = $data['status'] !== GatheringResponse::NO;

        GatheringResponse::query()->updateOrCreate(
            ['event_id' => $event->id, 'household_id' => $me->household_id],
            [
                'status' => $data['status'],
                'adults' => $coming ? (int) ($data['adults'] ?? 0) : 0,
                'children' => $coming ? (int) ($data['children'] ?? 0) : 0,
                'note' => $data['note'] ?? null,
                'responded_by' => $me->id,
            ],
        );

        return redirect()->route('events.show', $event)->with('status', __('Dankie, julle antwoord is gestoor.'));
    }

    public function addItem(Request $request, Event $event): RedirectResponse
    {
        $this->ensureGathering($event);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'mine' => ['nullable', 'boolean'],
        ]);

        $event->items()->create([
            'title' => $data['title'],
            'household_id' => $request->boolean('mine') ? $this->me()->household_id : null,
        ]);

        return redirect()->to(route('events.show', $event).'#bring');
    }

    /** "Ons bring dit" takes an open item; tapping it again on your own item lets it go. */
    public function claimItem(GatheringItem $item): RedirectResponse
    {
        $this->ensureGathering($item->event);
        $me = $this->me();

        if ($item->household_id === $me->household_id) {
            $item->update(['household_id' => null]);
        } elseif ($item->household_id === null) {
            $item->update(['household_id' => $me->household_id]);
        }

        return redirect()->to(route('events.show', $item->event).'#bring');
    }

    public function removeItem(GatheringItem $item): RedirectResponse
    {
        $this->ensureGathering($item->event);
        $event = $item->event;
        $item->delete();

        return redirect()->to(route('events.show', $event).'#bring');
    }

    private function ensureGathering(Event $event): void
    {
        $this->ensureVisible($event);
        abort_unless($event->isGathering(), 404);
    }
}
