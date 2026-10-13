<x-layout :title="__('Kalender')">
    @php($query = array_filter(['huis' => $filter['household'], 'wie' => $filter['member']]))

    <nav class="segments" aria-label="{{ __('Aansig') }}">
        <a href="{{ route('calendar', ['maand' => $monday->format('Y-m')] + $query) }}" @class(['is-active' => ! $weekView])>{{ __('Maand') }}</a>
        <a href="{{ route('calendar', ['aansig' => 'week', 'week' => $month->isSameMonth($today) ? $today->toDateString() : $month->toDateString()] + $query) }}" @class(['is-active' => $weekView])>{{ __('Week') }}</a>
    </nav>

    @if ($weekView)
        <div class="month-nav">
            <a class="button quiet round" href="{{ route('calendar', ['aansig' => 'week', 'week' => $monday->subWeek()->toDateString()] + $query) }}" aria-label="{{ __('Vorige week') }}">@include('partials.icon', ['name' => 'back'])</a>
            <h1 class="week-title">{{ __(':from tot :to', ['from' => $monday->translatedFormat('j M'), 'to' => $monday->addDays(6)->translatedFormat('j M')]) }}</h1>
            <a class="button quiet round" href="{{ route('calendar', ['aansig' => 'week', 'week' => $monday->addWeek()->toDateString()] + $query) }}" aria-label="{{ __('Volgende week') }}">@include('partials.icon', ['name' => 'next'])</a>
        </div>

        {{-- The week at a glance: a dot per colour on each day. Tap a day to jump to it, or to add to an empty one. --}}
        <div class="week-strip" aria-hidden="true">
            @for ($i = 0; $i < 7; $i++)
                @php($day = $monday->addDays($i))
                @php($dots = $weekDots->get($day->toDateString(), collect()))
                <a href="{{ $dots->isNotEmpty() ? '#d'.$day->toDateString() : route('events.create', ['datum' => $day->toDateString()]) }}" @class(['today' => $day->isSameDay($today)]) tabindex="-1">
                    <span class="wd">{{ \App\Support\Dates::shortDay($day) }}</span>
                    <b>{{ $day->day }}</b>
                    <span class="dots">@foreach ($dots as $colour)<i style="--c: {{ $colour }}"></i>@endforeach</span>
                </a>
            @endfor
        </div>
    @else
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

    @endif

    <nav class="filters" aria-label="{{ __('Wys net') }}">
        @php($keep = $weekView ? ['aansig' => 'week', 'week' => $monday->toDateString()] : ['maand' => $month->format('Y-m')])
        <a href="{{ route('calendar', $keep) }}" @class(['is-active' => ! $query])>{{ __('Almal') }}</a>
        @foreach ($households as $household)
            <a href="{{ route('calendar', $keep + ['huis' => $household->id]) }}"
               style="--c: {{ $household->colour }}"
               @class(['is-active' => $filter['household'] === $household->id])>
                <span class="dot"></span>{{ $household->name }}
            </a>
        @endforeach
    </nav>

    <form method="get" action="{{ route('calendar') }}" class="actions" style="margin-bottom: 8px">
        @foreach ($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
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
        <h2>{{ $weekView ? __('Hierdie week') : __('Hierdie maand') }}</h2>
        <span class="actions">
            <a class="button quiet small" href="{{ route('share.paste') }}">{{ __('Plak') }}</a>
            <a class="button quiet small" href="{{ route('polls.index') }}">{{ __('Vind ’n datum') }}</a>
            <a class="button small" href="{{ route('events.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('Nuwe') }}</a>
        </span>
    </div>

    @if ($days->isEmpty())
        <p class="empty">{{ $weekView ? __('Niks hierdie week nie.') : __('Niks hierdie maand nie.') }}</p>
    @else
        @include('partials.agenda', ['days' => $days, 'today' => $today])
    @endif
</x-layout>
