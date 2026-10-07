@php
    $statusLabel = ['yes' => __('Kom'), 'maybe' => __('Dalk'), 'no' => __('Kan nie')];
    $kindLabel = ['event' => __('Afspraak'), 'gathering' => __('Byeenkoms'), 'anniversary' => __('Herdenking'), 'school' => __('Skool')];
@endphp
<x-layout :title="$event->title">
    <a class="back" href="{{ route('calendar', ['maand' => $event->starts_on->format('Y-m')]) }}">@include('partials.icon', ['name' => 'back']) {{ __('Kalender') }}</a>

    <div class="head">
        <div>
            <p class="muted small"><span class="dot" style="--c: {{ $event->colour() }}"></span> {{ $kindLabel[$event->kind] ?? '' }} · {{ $event->isFamilyWide() ? __('Hele familie') : __('Net ons huis') }}</p>
            <h1>{{ $event->title }}</h1>
        </div>
        <a class="button quiet small" href="{{ route('events.edit', $event) }}">{{ __('Wysig') }}</a>
    </div>

    <dl class="facts">
        <dt>{{ __('Wanneer') }}</dt>
        <dd>
            {{ $event->starts_on->translatedFormat('l j F Y') }}
            @if ($event->ends_on && ! $event->ends_on->equalTo($event->starts_on))
                {{ __('tot :date', ['date' => $event->ends_on->translatedFormat('l j F')]) }}
            @endif
            @if ($event->timeLabel()) <br>{{ $event->timeLabel() }} @endif
            @if ($event->yearly) <br><span class="muted">{{ __('Elke jaar') }}</span> @endif
        </dd>
        @if ($event->isGathering() && $event->hostHousehold)
            <dt>{{ __('By') }}</dt><dd>{{ $event->hostHousehold->name }}</dd>
        @endif
        @if ($event->location)
            <dt>{{ __('Waar') }}</dt><dd>{{ $event->location }}</dd>
        @endif
        @if ($event->member)
            <dt>{{ __('Vir') }}</dt><dd>{{ $event->member->name }}</dd>
        @endif
        @if ($event->notes)
            <dt>{{ __('Notas') }}</dt><dd class="notes">{{ $event->notes }}</dd>
        @endif
    </dl>

    @if ($event->isGathering())
        <h2>{{ __('Kom julle?') }}</h2>
        <form method="post" action="{{ route('gatherings.respond', $event) }}" class="stack">
            @csrf
            <div class="choice" role="radiogroup" aria-label="{{ __('Ons antwoord') }}">
                @foreach ($statusLabel as $value => $label)
                    <label><input type="radio" name="status" value="{{ $value }}" @checked(old('status', $mine?->status) === $value) required><span>{{ $label }}</span></label>
                @endforeach
            </div>
            <div class="row">
                <label>{{ __('Grootmense') }}<input type="number" name="adults" min="0" max="30" inputmode="numeric" value="{{ old('adults', $mine?->adults ?? $me->household->adults()->count()) }}"></label>
                <label>{{ __('Kinders') }}<input type="number" name="children" min="0" max="30" inputmode="numeric" value="{{ old('children', $mine?->children ?? 0) }}"></label>
            </div>
            <label>{{ __('Boodskap') }} <span class="hint">{{ __('opsioneel') }}</span><input type="text" name="note" maxlength="255" value="{{ old('note', $mine?->note) }}"></label>
            <div><button type="submit">{{ $mine ? __('Verander ons antwoord') : __('Stuur ons antwoord') }}</button></div>
        </form>

        <h2>{{ __('Wie kom') }}</h2>
        <p class="muted">{{ trans_choice(':count grootmens|:count grootmense', $coming->sum('adults')) }}, {{ trans_choice(':count kind|:count kinders', $coming->sum('children')) }}</p>
        <ul class="rsvp">
            @foreach ($households as $household)
                @php($r = $responses->get($household->id))
                <li style="--c: {{ $household->colour }}">
                    <span>{{ $household->name }}@if ($r?->note)<br><span class="muted small">{{ $r->note }}</span>@endif</span>
                    @if ($r)
                        <span class="status-{{ $r->status }}">{{ $statusLabel[$r->status] }}@if ($r->status !== 'no') ({{ $r->adults + $r->children }})@endif</span>
                    @else
                        <span class="muted">{{ __('Nog nie geantwoord') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        <h2 id="bring">{{ __('Wie bring wat') }}</h2>
        @if ($allergies->isNotEmpty())
            <p class="panel small">
                <strong>{{ __('Onthou die allergieë:') }}</strong>
                @foreach ($allergies as $child)
                    {{ $child->firstName() }}: {{ $child->allergies }}@if (! $loop->last); @endif
                @endforeach
            </p>
        @endif
        <ul class="items">
            @forelse ($event->items as $item)
                <li class="item">
                    <span class="dot" style="--c: {{ $item->household->colour ?? 'var(--line)' }}"></span>
                    <span class="title">
                        <span>{{ $item->title }}</span>
                        <span class="meta">{{ $item->household?->name ?? __('Nog oop') }}</span>
                    </span>
                    @if ($item->household_id === null || $item->household_id === $me->household_id)
                        <form method="post" action="{{ route('gatherings.items.claim', $item) }}">
                            @csrf
                            <button type="submit" class="small {{ $item->household_id ? 'quiet' : '' }}">{{ $item->household_id ? __('Los') : __('Ons bring dit') }}</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('gatherings.items.destroy', $item) }}" data-confirm="{{ __('Haal “:title” van die lys af?', ['title' => $item->title]) }}">
                        @csrf @method('delete')
                        <button type="submit" class="remove" aria-label="{{ __('Verwyder') }}">@include('partials.icon', ['name' => 'trash'])</button>
                    </form>
                </li>
            @empty
                <li class="empty">{{ __('Nog niks op die lys nie. Voeg by wat nodig is, of wat julle gaan bring.') }}</li>
            @endforelse
        </ul>
        <form method="post" action="{{ route('gatherings.items.store', $event) }}" class="stack" style="margin-top: 12px">
            @csrf
            <div class="add-item" style="margin: 0">
                <label class="visually-hidden" for="bring-title">{{ __('Wat moet iemand bring?') }}</label>
                <input id="bring-title" type="text" name="title" maxlength="255" required placeholder="{{ __('Bv. slaai, poeding, ys') }}">
                <button type="submit">{{ __('Voeg by') }}</button>
            </div>
            <label class="check"><input type="checkbox" name="mine" value="1"> {{ __('Ons bring dit') }}</label>
        </form>
    @endif
</x-layout>
