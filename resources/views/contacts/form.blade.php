@php
    $editing = $contact->exists;
    $labels = ['emergency' => __('Noodnommer'), 'doctor' => __('Dokter'), 'school' => __('Skool'), 'babysitter' => __('Oppasser'), 'other' => __('Ander')];
    $share = old('share', $editing && $contact->household_id !== null ? 'household' : 'family');
@endphp
<x-layout :title="$editing ? $contact->name : __('Nuwe nommer')">
    <a class="back" href="{{ route('contacts.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Belangrike nommers') }}</a>
    <h1>{{ $editing ? $contact->name : __('Nuwe nommer') }}</h1>

    <form method="post" action="{{ $editing ? route('contacts.update', $contact) : route('contacts.store') }}" class="stack" style="margin-top: 16px">
        @csrf
        @if ($editing) @method('put') @endif
        <label>{{ __('Naam') }}<input type="text" name="name" maxlength="255" required value="{{ old('name', $contact->name) }}">
            @error('name')<span class="error">{{ $message }}</span>@enderror</label>
        <label>{{ __('Soort') }}
            <select name="category">
                @foreach ($labels as $value => $label)
                    <option value="{{ $value }}" @selected(old('category', $contact->category) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ __('Telefoon') }}<input type="tel" name="phone" maxlength="30" value="{{ old('phone', $contact->phone) }}"></label>
        <label>{{ __('E-pos') }}<input type="email" name="email" maxlength="255" value="{{ old('email', $contact->email) }}">
            @error('email')<span class="error">{{ $message }}</span>@enderror</label>
        <label>{{ __('Notas') }}<textarea name="notes" maxlength="2000">{{ old('notes', $contact->notes) }}</textarea></label>
        <fieldset>
            <legend>{{ __('Wie sien dit?') }}</legend>
            <div class="choice">
                <label><input type="radio" name="share" value="household" @checked($share === 'household')><span>{{ __('Net ons huis') }}</span></label>
                <label><input type="radio" name="share" value="family" @checked($share === 'family')><span>{{ __('Hele familie') }}</span></label>
            </div>
        </fieldset>
        <div class="actions">
            <button type="submit">{{ $editing ? __('Stoor') : __('Voeg by') }}</button>
            <a class="button quiet" href="{{ route('contacts.index') }}">{{ __('Kanselleer') }}</a>
        </div>
    </form>

    @if ($editing)
        <form method="post" action="{{ route('contacts.destroy', $contact) }}" data-confirm="{{ __('Verwyder :name?', ['name' => $contact->name]) }}" style="margin-top: 28px">
            @csrf @method('delete')
            <button type="submit" class="danger">{{ __('Verwyder') }}</button>
        </form>
    @endif
</x-layout>
