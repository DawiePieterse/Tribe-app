<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Lift;
use App\Services\Agenda;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** "Wie ry?": who takes and who fetches, for each coming day of a child's activity. */
class LiftController extends Controller
{
    /** How far ahead lifts are planned. */
    public const DAYS_AHEAD = 28;

    public function store(Request $request, Event $event, Agenda $agenda): RedirectResponse
    {
        $this->ensureVisible($event);
        abort_unless($event->isForChild(), 404);

        $dates = array_map(fn (CarbonImmutable $d) => $d->toDateString(), self::dates($event, $agenda));
        $data = $request->validate([
            'lifts' => ['required', 'array'],
            'lifts.*' => ['array'],
            'lifts.*.there' => ['nullable', Rule::exists('members', 'id')],
            'lifts.*.back' => ['nullable', Rule::exists('members', 'id')],
        ]);

        foreach ($data['lifts'] as $date => $who) {
            if (! in_array($date, $dates, true)) {
                continue;
            }
            foreach (Lift::DIRECTIONS as $direction) {
                Lift::query()->updateOrCreate(
                    ['event_id' => $event->id, 'date' => $date, 'direction' => $direction],
                    ['member_id' => ($who[$direction] ?? null) ?: null],
                );
            }
        }

        return redirect()->to(route('events.show', $event).'#ry')->with('status', __('Gestoor.'));
    }

    /**
     * The coming days of this event that need a lift.
     *
     * @return list<CarbonImmutable>
     */
    public static function dates(Event $event, Agenda $agenda): array
    {
        $today = CarbonImmutable::today();

        if (! $event->repeats()) {
            return $event->starts_on->greaterThanOrEqualTo($today) ? [$event->starts_on] : [];
        }

        return $agenda->repeatDates($event, $today, $today->addDays(self::DAYS_AHEAD));
    }
}
