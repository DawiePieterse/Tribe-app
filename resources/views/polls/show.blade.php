@php($labels = ['yes' => __('Werk'), 'maybe' => __('Dalk'), 'no' => __('Nee')])
<x-layout :title="$poll->title">
    <a class="back" href="{{ route('polls.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Vind ’n datum') }}</a>
    <h1>{{ $poll->title }}</h1>
    <p class="muted">{{ $poll->creator ? __('Voorgestel deur :name', ['name' => $poll->creator->name]) : '' }}</p>
    @if ($poll->notes)<p class="notes">{{ $poll->notes }}</p>@endif
    @if ($poll->isOpen())
        <p>
            <x-message class="button quiet small" :text="__('Watter datums werk vir julle vir :title? Merk dit in Tribe: :link', ['title' => $poll->title, 'link' => route('polls.show', $poll)])" :label="__('Vra die familie')" />
        </p>
    @endif

    @if (! $poll->isOpen())
        <p class="panel">
            {{ __('Die datum is gekies.') }}
            @if ($poll->event)<a href="{{ route('events.show', $poll->event) }}">{{ $poll->event->starts_on->translatedFormat('l j F') }}</a>@endif
        </p>
    @endif

    <form method="post" action="{{ route('polls.vote', $poll) }}" class="stack">
        @csrf
        <ul class="poll">
            @foreach ($poll->options as $option)
                <li @class(['is-best' => $option->id === $bestId])>
                    <div class="poll-date">
                        <strong>{{ $option->date->translatedFormat('l j F') }}@if ($option->time) {{ substr($option->time, 0, 5) }}@endif</strong>
                        @if (in_array($option->id, $everyone))
                            <span class="tag">{{ __('Pas almal') }}</span>
                        @elseif ($option->id === $bestId)
                            <span class="tag">{{ __('Pas die meeste') }}</span>
                        @endif
                    </div>
                    <div class="poll-votes" aria-label="{{ __('Wat die huise gesê het') }}">
                        @foreach ($households as $household)
                            @php($answer = $option->votes->firstWhere('household_id', $household->id)?->answer)
                            <span class="vote vote-{{ $answer ?? 'none' }}" style="--c: {{ $household->colour }}" title="{{ $household->name }}">
                                <span class="dot"></span> {{ $household->name }}: {{ $answer ? $labels[$answer] : __('nog nie') }}
                            </span>
                        @endforeach
                    </div>
                    @if ($poll->isOpen())
                        <div class="choice" role="radiogroup" aria-label="{{ __('Ons keuse vir :date', ['date' => $option->date->translatedFormat('j F')]) }}">
                            @foreach ($labels as $value => $label)
                                <label><input type="radio" name="answers[{{ $option->id }}]" value="{{ $value }}" @checked(($mine[$option->id] ?? null) === $value) required><span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($poll->isOpen())
            <div><button type="submit">{{ __('Stoor ons keuses') }}</button></div>
        @endif
    </form>

    @if ($poll->isOpen())
        <h2>{{ __('Kies die datum') }}</h2>
        <p class="muted small">{{ __('Wanneer julle weet, maak dit ’n byeenkoms. Dan kan almal laat weet of hulle kom en wat hulle bring.') }}</p>
        <div class="actions">
            @foreach ($poll->options as $option)
                <form method="post" action="{{ route('polls.choose', $option) }}">
                    @csrf
                    <button type="submit" class="{{ $option->id === $bestId ? '' : 'quiet' }} small">{{ \App\Support\Dates::shortDay($option->date) }} {{ $option->date->translatedFormat('j M') }}</button>
                </form>
            @endforeach
        </div>
    @endif

    @if ($canManage)
        <form method="post" action="{{ route('polls.destroy', $poll) }}" data-confirm="{{ __('Verwyder “:title”?', ['title' => $poll->title]) }}" style="margin-top: 28px">
            @csrf @method('delete')
            <button type="submit" class="danger">{{ __('Verwyder') }}</button>
        </form>
    @endif
</x-layout>
