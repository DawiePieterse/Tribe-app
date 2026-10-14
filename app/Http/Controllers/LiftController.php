<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Lift;
use App\Models\Member;
use App\Services\Agenda;
use App\Services\Push;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The coming days of an entry: who takes and fetches a child when it isn't the usual person, and days that fall away. */
class LiftController extends Controller
{
    public function store(Request $request, Event $event, Agenda $agenda, Push $push): RedirectResponse
    {
        $this->ensureVisible($event);
        abort_unless($event->isForChild(), 404);

        $dates = self::dateStrings($agenda->comingDates($event));
        $data = $request->validate([
            'lifts' => ['required', 'array'],
            'lifts.*' => ['array'],
            'lifts.*.there' => ['nullable', 'integer', 'min:0'],
            'lifts.*.back' => ['nullable', 'integer', 'min:0'],
        ]);
        $drivers = Member::query()->drivers()->pluck('id')->all();
        $existing = $event->lifts()->whereIn('date', $dates)->get();

        $forget = [];
        $save = [];
        $told = [];
        foreach ($data['lifts'] as $date => $who) {
            if (! in_array($date, $dates, true)) {
                continue;
            }
            $day = CarbonImmutable::parse($date);
            foreach (Lift::DIRECTIONS as $direction) {
                $value = (string) ($who[$direction] ?? '');
                $current = $existing->first(fn (Lift $l) => $l->direction === $direction && $l->date->isSameDay($day));

                // "As usual": no exception for this day.
                if ($value === '') {
                    if ($current !== null) {
                        $forget[] = $current->id;
                    }

                    continue;
                }

                // "0" is nobody; anything else must be someone who can drive.
                $memberId = $value === '0' ? null : (int) $value;
                if (($memberId !== null && ! in_array($memberId, $drivers, true)) || ($current !== null && $current->member_id === $memberId)) {
                    continue;
                }

                $save[] = ['event_id' => $event->id, 'date' => $date, 'direction' => $direction, 'member_id' => $memberId, 'created_at' => now(), 'updated_at' => now()];
                // Someone put down for a lift by someone else hears about it.
                if ($memberId !== null && $memberId !== $this->me()->id) {
                    $told[$memberId][] = ($direction === Lift::THERE ? __('neem') : __('haal')).' '.Dates::short($day);
                }
            }
        }

        Lift::query()->whereKey($forget)->delete();
        Lift::query()->upsert($save, ['event_id', 'date', 'direction'], ['member_id', 'updated_at']);

        if ($told !== [] && $push->enabled()) {
            foreach (Member::query()->findMany(array_keys($told)) as $member) {
                $push->send($member, __('Jy ry: :title (:child)', ['title' => $event->title, 'child' => $event->member?->firstName()]),
                    __('Jy :legs', ['legs' => implode(', ', $told[$member->id])]).($event->timeLabel() ? ' · '.$event->timeLabel() : ''),
                    route('events.show', $event).'#kere');
            }
        }

        return redirect()->to(route('events.show', $event).'#kere')->with('status', __('Gestoor.'));
    }

    /** One day of a repeating entry falls away (or comes back). */
    public function skip(Request $request, Event $event, Agenda $agenda): RedirectResponse
    {
        $this->ensureVisible($event);
        abort_unless($event->canSkipDays(), 404);

        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'skip' => ['required', 'boolean']]);
        abort_unless(in_array($data['date'], self::dateStrings($agenda->comingDates($event)), true), 422);

        if ($request->boolean('skip')) {
            $event->skips()->firstOrCreate(['date' => $data['date']]);
            $status = __('Dié keer val weg.');
        } else {
            $event->skips()->where('date', $data['date'])->delete();
            $status = __('Dié keer is terug.');
        }

        // The entry changed: connected calendars catch up (SyncSoon listens for saves).
        $event->touch();

        return redirect()->to(route('events.show', $event).'#kere')->with('status', $status);
    }

    /**
     * @param  list<CarbonImmutable>  $dates
     * @return list<string>
     */
    private static function dateStrings(array $dates): array
    {
        return array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates);
    }
}
