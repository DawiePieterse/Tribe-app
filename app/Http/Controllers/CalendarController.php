<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Member;
use App\Services\Agenda;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __invoke(Request $request, Agenda $agenda): View
    {
        $me = $this->me();
        $month = $this->month((string) $request->query('maand', ''));
        $weekView = $request->query('aansig') === 'week';
        $monday = $this->monday((string) $request->query('week', ''));

        $filter = [
            'household' => $request->integer('huis') ?: null,
            'member' => $request->integer('wie') ?: null,
        ];

        $occurrences = $weekView
            ? $agenda->between($me, $monday, $monday->addDays(6), $filter)
            : $agenda->between($me, $month, $month->endOfMonth(), $filter);

        return view('calendar.index', [
            'weekView' => $weekView,
            'monday' => $monday,
            // The colours on each day of the week strip (a child's ballet, a family gathering).
            'weekDots' => $occurrences->groupBy(fn ($o) => $o->date->toDateString())->map(fn ($day) => $day->pluck('colour')->unique()->take(4)->values()),
            'month' => $month,
            'previous' => $month->subMonth(),
            'next' => $month->addMonth(),
            'days' => $occurrences->groupBy(fn ($o) => $o->date->toDateString()),
            'busyDays' => $occurrences->map(fn ($o) => $o->date->day)->unique()->all(),
            'households' => Household::query()->orderBy('id')->get(),
            'members' => Member::query()->orderBy('name')->get(),
            'filter' => $filter,
            'today' => CarbonImmutable::today(),
        ]);
    }

    private function monday(string $value): CarbonImmutable
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? CarbonImmutable::parse($value) : CarbonImmutable::today();

        return $date->startOfWeek(CarbonInterface::MONDAY);
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
