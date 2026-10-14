<x-layout :title="__('Tuis')">
    <header class="greeting">
        <h1>{{ __('Hallo :name', ['name' => $me->firstName()]) }}</h1>
        <p>{{ $today->translatedFormat('l j F') }}</p>
        @if ($weather)
            <div class="weather">@if ($me->household->townName())<strong>{{ $me->household->townName() }}:</strong> @endif{{ \App\Services\Weather::line($weather) }}@foreach ($weather['flags'] as $flag) <span class="flag">{{ $flag }}</span>@endforeach</div>
        @endif
    </header>

    @if ($inbox->isNotEmpty())
        <section class="panel">
            <h2>{{ __('Gestuur na Tribe') }}</h2>
            <ul>
                @foreach ($inbox as $item)
                    @php($s = $item->suggestion ?? [])
                    <li class="inbox-item">
                        <a class="entry" href="{{ route('inbox.open', $item) }}">
                            <strong>{{ $s['title'] ?? __('Nuwe plan') }}</strong>
                            <span class="meta">
                                @if (! empty($s['starts_on'])){{ \Carbon\CarbonImmutable::parse($s['starts_on'])->translatedFormat('l j F') }}@if (! empty($s['start_time'])) {{ $s['start_time'] }}@endif · @endif{{ __('tik om te bevestig') }}
                            </span>
                        </a>
                        <form method="post" action="{{ route('inbox.discard', $item) }}">
                            @csrf @method('delete')
                            <button type="submit" class="remove" aria-label="{{ __('Gooi weg') }}">@include('partials.icon', ['name' => 'trash'])</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($lifts->isNotEmpty())
        <section class="panel">
            <h2>{{ __('Wie ry hierdie week') }}</h2>
            <ul>
                @foreach ($lifts as $o)
                    <li>
                        <a class="entry" style="--c: {{ $o->colour }}" href="{{ route('events.show', $o->event) }}#kere">
                            <strong>{{ $o->title }}@if ($o->event?->member) · {{ $o->event->member->firstName() }}@endif</strong>
                            <span class="meta">{{ $o->date->isSameDay($today) ? __('vandag') : \App\Support\Dates::short($o->date) }}@if ($o->time) {{ $o->time }}@endif</span>
                            @include('partials.lift-line')
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($birthdaysSoon->isNotEmpty())
        <section class="panel">
            <h2>{{ __('Verjaar binnekort') }}</h2>
            <ul>
                @foreach ($birthdaysSoon as $o)
                    <li class="line">
                        <span class="dot" style="--c: {{ $o->colour }}"></span>
                        <strong>{{ $o->member->name }}</strong>
                        <span class="muted">
                            {{ $o->date->isSameDay($today) ? __('vandag') : $o->date->translatedFormat('l j F') }}@if ($o->detail), {{ $o->detail }}@endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($toAnswer->isNotEmpty())
        <section class="panel">
            <h2>{{ __('Laat weet of julle kom') }}</h2>
            <ul>
                @foreach ($toAnswer as $event)
                    <li>
                        <a class="entry" @if ($event->hostHousehold) style="--c: {{ $event->hostHousehold->colour }}" @endif href="{{ route('events.show', $event) }}">
                            <strong>{{ $event->title }}</strong>
                            <span class="meta">{{ $event->starts_on->translatedFormat('l j F') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($polls->isNotEmpty())
        <section class="panel">
            <h2>{{ __('Watter datums werk vir julle?') }}</h2>
            <ul>
                @foreach ($polls as $poll)
                    <li>
                        <a class="entry" href="{{ route('polls.show', $poll) }}">
                            <strong>{{ $poll->title }}</strong>
                            <span class="meta">{{ trans_choice(':count datum om te merk|:count datums om te merk', $poll->options->count()) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($shopping)
        <p>
            <a class="card plain" href="{{ route('lists.show', $shopping) }}">
                <span>@include('partials.icon', ['name' => 'list']) <strong>{{ $shopping->name }}</strong></span>
                <span class="badge" aria-label="{{ trans_choice(':count item oop|:count items oop', $shopping->open_count) }}">{{ $shopping->open_count }}</span>
            </a>
        </p>
    @endif

    <div class="head">
        <h2>{{ __('Die komende week') }}</h2>
        <a class="button small" href="{{ route('events.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('Nuwe') }}</a>
    </div>

    @if ($days->isEmpty())
        <p class="empty">{{ __('Niks op die kalender vir die volgende sewe dae nie.') }} <a href="{{ route('events.create') }}">{{ __('Beplan iets') }}</a></p>
    @else
        @include('partials.agenda', ['days' => $days, 'today' => $today])
    @endif
</x-layout>
