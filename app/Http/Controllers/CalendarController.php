<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Member;
use App\Services\Agenda;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __invoke(Request $request, Agenda $agenda): View
    {
        $me = $this->me();
        $month = $this->month((string) $request->query('maand', ''));
        // The week is the default; a month shows when asked for (the Maand switch and month links carry ?maand=).
        $weekView = $request->query('aansig') === 'week'
            || ($request->query('aansig') !== 'maand' && ! $request->has('maand'));
        $weekStart = Dates::weekStartOf((string) $request->query('week', ''));

        $filter = [
            'household' => $request->integer('huis') ?: null,
            'member' => $request->integer('wie') ?: null,
        ];

        $occurrences = $weekView
            ? $agenda->between($me, $weekStart, $weekStart->addDays(6), $filter)
            : $agenda->between($me, $month, $month->endOfMonth(), $filter);

        $days = $occurrences->groupBy(fn ($o) => $o->date->toDateString());

        return view('calendar.index', [
            'weekView' => $weekView,
            'weekStart' => $weekStart,
            // The colours on each day of the week strip (a child's ballet, a family gathering).
            'weekDots' => $weekView ? $days->map(fn ($day) => $day->pluck('colour')->unique()->take(4)->values()) : collect(),
            'month' => $month,
            'previous' => $month->subMonth(),
            'next' => $month->addMonth(),
            'days' => $days,
            'busyDays' => $occurrences->map(fn ($o) => $o->date->day)->unique()->all(),
            'households' => Household::query()->orderBy('id')->get(),
            'members' => Member::query()->orderBy('name')->get(),
            'filter' => $filter,
            'today' => CarbonImmutable::today(),
        ]);
    }

    private function month(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            $month = CarbonImmutable::createFromFormat('!Y-m', $value);

            if ($month !== null) {
                return $month->startOfMonth();
            }
        }

        return CarbonImmutable::today()->startOfMonth();
    }
}
