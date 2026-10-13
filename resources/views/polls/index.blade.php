<x-layout :title="__('Vind ’n datum')">
    <a class="back" href="{{ route('calendar') }}">@include('partials.icon', ['name' => 'back']) {{ __('Kalender') }}</a>
    <div class="head">
        <h1>{{ __('Vind ’n datum') }}</h1>
        <a class="button small" href="{{ route('polls.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('Nuwe') }}</a>
    </div>
    <p class="muted">{{ __('Stel ’n paar datums voor; elke huis merk wat werk, en Tribe wys watter dag die meeste pas.') }}</p>

    <ul class="cards">
        @forelse ($open as $poll)
            <li>
                <a class="card" href="{{ route('polls.show', $poll) }}">
                    <span>
                        <strong>{{ $poll->title }}</strong><br>
                        <span class="muted small">{{ $poll->options->map(fn ($o) => $o->date->translatedFormat('j M'))->implode(', ') }}</span>
                    </span>
                </a>
            </li>
        @empty
            <li class="empty">{{ __('Niks oop nie. Beplan iets en kry almal se datums.') }}</li>
        @endforelse
    </ul>

    @if ($closed->isNotEmpty())
        <h2>{{ __('Klaar gekies') }}</h2>
        <ul class="cards">
            @foreach ($closed as $poll)
                <li>
                    <a class="card" href="{{ $poll->event ? route('events.show', $poll->event) : route('polls.show', $poll) }}">
                        <span><strong>{{ $poll->title }}</strong>@if ($poll->event)<br><span class="muted small">{{ $poll->event->starts_on->translatedFormat('l j F') }}</span>@endif</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layout>
