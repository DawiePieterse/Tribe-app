@php
    $editing = $event->exists;
    $kind = old('kind', $event->kind);
    $share = old('share', $event->household_id === null && $editing ? 'family' : ($event->household_id === null && in_array($kind, ['anniversary', 'school', 'gathering']) ? 'family' : 'household'));
    $kinds = [
        'event' => __('Afspraak of uitstappie'),
        'gathering' => __('Byeenkoms (met wie kom en wie bring wat)'),
        'anniversary' => __('Herdenking (elke jaar)'),
        'school' => __('Skoolvakansie of kwartaal'),
    ];
@endphp
<x-layout :title="$editing ? __('Wysig') : __('Nuwe inskrywing')">
    <a class="back" href="{{ $editing ? route('events.show', $event) : route('calendar') }}">@include('partials.icon', ['name' => 'back']) {{ __('Terug') }}</a>
    <h1>{{ $editing ? __('Wysig') : __('Nuwe inskrywing') }}</h1>

    <form method="post" action="{{ $editing ? route('events.update', $event) : route('events.store') }}" class="stack" style="margin-top: 16px">
        @csrf
        @if ($editing) @method('put') @endif

        <fieldset>
            <legend>{{ __('Wat is dit?') }}</legend>
            <select name="kind" data-kind>
                @foreach ($kinds as $value => $label)
                    <option value="{{ $value }}" @selected($kind === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </fieldset>

        <label>
            {{ __('Naam') }}
            <input type="text" name="title" value="{{ old('title', $event->title) }}" required maxlength="255"
                   placeholder="{{ __('Bv. Sondagete by Ouma-hulle') }}">
            @error('title')<span class="error">{{ $message }}</span>@enderror
        </label>

        <div class="row">
            <label>
                {{ __('Datum') }}
                <input type="date" name="starts_on" value="{{ old('starts_on', $event->starts_on?->toDateString()) }}" required>
                @error('starts_on')<span class="error">{{ $message }}</span>@enderror
            </label>
            <label>
                {{ __('Tot en met') }} <span class="hint">{{ __('as dit langer as een dag is') }}</span>
                <input type="date" name="ends_on" value="{{ old('ends_on', $event->ends_on?->toDateString()) }}">
                @error('ends_on')<span class="error">{{ $message }}</span>@enderror
            </label>
        </div>

        <div class="row">
            <label>
                {{ __('Van') }} <span class="hint">{{ __('los oop vir heeldag') }}</span>
                <input type="time" name="start_time" value="{{ old('start_time', $event->start_time ? substr($event->start_time, 0, 5) : '') }}">
                @error('start_time')<span class="error">{{ $message }}</span>@enderror
            </label>
            <label>
                {{ __('Tot') }}
                <input type="time" name="end_time" value="{{ old('end_time', $event->end_time ? substr($event->end_time, 0, 5) : '') }}">
                @error('end_time')<span class="error">{{ $message }}</span>@enderror
            </label>
        </div>

        <label>
            {{ __('Waar') }}
            <input type="text" name="location" value="{{ old('location', $event->location) }}" maxlength="255">
        </label>

        <label data-show-for="gathering">
            {{ __('Wie se huis?') }}
            <select name="host_household_id">
                @foreach ($households as $household)
                    <option value="{{ $household->id }}" @selected((int) old('host_household_id', $event->host_household_id) === $household->id)>{{ $household->name }}</option>
                @endforeach
            </select>
        </label>

        <label>
            {{ __('Vir wie?') }} <span class="hint">{{ __('kies iemand as dit net een persoon se afspraak is') }}</span>
            <select name="member_id">
                <option value="">{{ __('Niemand spesifiek nie') }}</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}" @selected((int) old('member_id', $event->member_id) === $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
        </label>

        <fieldset data-hide-for="gathering">
            <legend>{{ __('Wie sien dit?') }}</legend>
            <div class="choice">
                <label><input type="radio" name="share" value="household" @checked($share === 'household')><span>{{ __('Net ons huis') }}</span></label>
                <label><input type="radio" name="share" value="family" @checked($share === 'family')><span>{{ __('Hele familie') }}</span></label>
            </div>
        </fieldset>
        <p class="muted small" data-show-for="gathering">{{ __('Byeenkomste is altyd vir die hele familie.') }}</p>

        <label>
            {{ __('Notas') }}
            <textarea name="notes" maxlength="5000">{{ old('notes', $event->notes) }}</textarea>
        </label>

        <div class="actions">
            <button type="submit">{{ $editing ? __('Stoor') : __('Voeg by') }}</button>
            <a class="button quiet" href="{{ $editing ? route('events.show', $event) : route('calendar') }}">{{ __('Kanselleer') }}</a>
        </div>
    </form>

    @if ($editing)
        <form method="post" action="{{ route('events.destroy', $event) }}" data-confirm="{{ __('Verwyder “:title”?', ['title' => $event->title]) }}" style="margin-top: 28px">
            @csrf @method('delete')
            <button type="submit" class="danger">@include('partials.icon', ['name' => 'trash']) {{ __('Verwyder') }}</button>
        </form>
    @endif
</x-layout>
