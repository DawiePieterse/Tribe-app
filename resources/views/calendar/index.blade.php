<x-layout :title="__('Kalender')">
    @php($query = array_filter(['huis' => $filter['household'], 'wie' => $filter['member']]))

    <div class="month-nav">
        <a class="button quiet round" href="{{ route('calendar', ['maand' => $previous->format('Y-m')] + $query) }}" aria-label="{{ __('Vorige maand') }}">@include('partials.icon', ['name' => 'back'])</a>
        <h1>{{ $month->translatedFormat('F Y') }}</h1>
        <a class="button quiet round" href="{{ route('calendar', ['maand' => $next->format('Y-m')] + $query) }}" aria-label="{{ __('Volgende maand') }}">@include('partials.icon', ['name' => 'next'])</a>
    </div>

    {{-- Month at a glance; a dot marks a day with something on. Tap a day to add to it. --}}
    <div class="grid" aria-hidden="true">
        @foreach (['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Sa', 'So'] as $wd)
            <span class="wd">{{ __($wd) }}</span>
        @endforeach
        @for ($i = 1; $i < $month->dayOfWeekIso; $i++)
            <span class="cell"></span>
        @endfor
        @for ($d = 1; $d <= $month->daysInMonth; $d++)
            @php($date = $month->setDay($d))
            <a href="{{ in_array($d, $busyDays) ? '#d'.$date->toDateString() : route('events.create', ['datum' => $date->toDateString()]) }}"
               @class(['busy' => in_array($d, $busyDays), 'today' => $date->isSameDay($today)])
               tabindex="-1">{{ $d }}</a>
        @endfor
    </div>

    <nav class="filters" aria-label="{{ __('Wys net') }}">
        <a href="{{ route('calendar', ['maand' => $month->format('Y-m')]) }}" @class(['is-active' => ! $query])>{{ __('Almal') }}</a>
        @foreach ($households as $household)
            <a href="{{ route('calendar', ['maand' => $month->format('Y-m'), 'huis' => $household->id]) }}"
               style="--c: {{ $household->colour }}"
               @class(['is-active' => $filter['household'] === $household->id])>
                <span class="dot"></span>{{ $household->name }}
            </a>
        @endforeach
    </nav>

    <form method="get" action="{{ route('calendar') }}" class="actions" style="margin-bottom: 8px">
        <input type="hidden" name="maand" value="{{ $month->format('Y-m') }}">
        <label class="visually-hidden" for="wie">{{ __('Een persoon') }}</label>
        <select id="wie" name="wie" data-autosubmit style="flex: 1">
            <option value="">{{ __('Een persoon se kalender…') }}</option>
            @foreach ($members as $member)
                <option value="{{ $member->id }}" @selected($filter['member'] === $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="quiet">{{ __('Wys') }}</button></noscript>
    </form>

    <div class="head">
        <h2>{{ __('Hierdie maand') }}</h2>
        <a class="button small" href="{{ route('events.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('Nuwe') }}</a>
    </div>

    @if ($days->isEmpty())
        <p class="empty">{{ __('Niks hierdie maand nie.') }}</p>
    @else
        @include('partials.agenda', ['days' => $days, 'today' => $today])
    @endif
</x-layout>
