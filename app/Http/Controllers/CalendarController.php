<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Member;
use App\Services\Agenda;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __invoke(Request $request, Agenda $agenda): View
    {
        $me = $this->me();
        $month = $this->month((string) $request->query('maand', ''));

        $filter = [
            'household' => $request->integer('huis') ?: null,
            'member' => $request->integer('wie') ?: null,
        ];

        $occurrences = $agenda->between($me, $month, $month->endOfMonth(), $filter);

        return view('calendar.index', [
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
