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
    /** How far ahead lifts are planned. */
    public const DAYS_AHEAD = 28;

    public function store(Request $request, Event $event, Agenda $agenda, Push $push): RedirectResponse
    {
        $this->ensureVisible($event);
        abort_unless($event->isForChild(), 404);

        $dates = array_map(fn (CarbonImmutable $d) => $d->toDateString(), self::dates($event, $agenda));
        $data = $request->validate([
            'lifts' => ['required', 'array'],
            'lifts.*' => ['array'],
            'lifts.*.there' => ['nullable', 'integer', 'min:0'],
            'lifts.*.back' => ['nullable', 'integer', 'min:0'],
        ]);
        $drivers = Member::query()->drivers()->pluck('id')->all();

        $told = [];
        foreach ($data['lifts'] as $date => $who) {
            if (! in_array($date, $dates, true)) {
                continue;
            }
            foreach (Lift::DIRECTIONS as $direction) {
                $value = (string) ($who[$direction] ?? '');
                $keys = ['event_id' => $event->id, 'date' => $date, 'direction' => $direction];

                // "As usual": no exception for this day.
                if ($value === '') {
                    Lift::query()->where($keys)->delete();

                    continue;
                }

                $memberId = $value === '0' ? null : (int) $value;
                if ($memberId !== null && ! in_array($memberId, $drivers, true)) {
                    continue;
                }
                $lift = Lift::query()->updateOrCreate($keys, ['member_id' => $memberId]);
                // Someone put down for a lift by someone else hears about it.
                if ($memberId !== null && $memberId !== $this->me()->id && ($lift->wasRecentlyCreated || $lift->wasChanged('member_id'))) {
                    $told[$memberId][] = ($direction === Lift::THERE ? __('neem') : __('haal')).' '.Dates::shortDay($lift->date).' '.$lift->date->translatedFormat('j M');
                }
            }
        }

        foreach (Member::query()->findMany(array_keys($told)) as $member) {
            $push->send($member, __('Jy ry: :title (:child)', ['title' => $event->title, 'child' => $event->member?->firstName()]),
                __('Jy :legs', ['legs' => implode(', ', $told[$member->id])]).($event->timeLabel() ? ' · '.$event->timeLabel() : ''),
                route('events.show', $event).'#kere');
        }

        return redirect()->to(route('events.show', $event).'#kere')->with('status', __('Gestoor.'));
    }

    /** One day of a repeating entry falls away (or comes back). */
    public function skip(Request $request, Event $event, Agenda $agenda): RedirectResponse
    {
        $this->ensureVisible($event);
        abort_unless($event->repeats() && ! $event->isFromFeed(), 404);

        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'skip' => ['required', 'boolean']]);
        $dates = array_map(fn (CarbonImmutable $d) => $d->toDateString(), self::dates($event, $agenda));
        abort_unless(in_array($data['date'], $dates, true), 422);

        if ($request->boolean('skip')) {
            $event->skips()->firstOrCreate(['date' => $data['date']]);
            $status = __('Dié keer val weg.');
        } else {
            $event->skips()->where('date', $data['date'])->delete();
            $status = __('Dié keer is terug.');
        }

        return redirect()->to(route('events.show', $event).'#kere')->with('status', $status);
    }

    /**
     * The coming days of this event (skipped ones included, so they can be brought back).
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
