<x-layout :title="__('Tuis')">
    <header class="greeting">
        <h1>{{ __('Hallo :name', ['name' => $me->firstName()]) }}</h1>
        <p>{{ $today->translatedFormat('l j F') }}</p>
    </header>

    @if ($birthdaysSoon->isNotEmpty())
        <section class="panel">
            <h2>{{ __('Verjaar binnekort') }}</h2>
            <ul>
                @foreach ($birthdaysSoon as $o)
                    <li>
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
                        <a class="entry" style="--c: {{ $event->hostHousehold->colour ?? '#1F5C99' }}" href="{{ route('events.show', $event) }}">
                            <strong>{{ $event->title }}</strong>
                            <span class="meta">{{ $event->starts_on->translatedFormat('l j F') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($shopping)
        <p>
            <a class="card" href="{{ route('lists.show', $shopping) }}">
                <span>@include('partials.icon', ['name' => 'list']) <strong>{{ $shopping->name }}</strong></span>
                <span class="badge" aria-label="{{ trans_choice(':count item oop|:count items oop', $shopping->open_count) }}">{{ $shopping->open_count }}</span>
            </a>
        </p>
    @endif

    <div class="head">
        <h2>{{ __('Wat kom') }}</h2>
        <a class="button small" href="{{ route('events.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('Nuwe') }}</a>
    </div>

    @if ($days->isEmpty())
        <p class="empty">{{ __('Niks op die kalender vir die volgende ses weke nie.') }} <a href="{{ route('events.create') }}">{{ __('Beplan iets') }}</a></p>
    @else
        @include('partials.agenda', ['days' => $days, 'today' => $today])
    @endif
</x-layout>
