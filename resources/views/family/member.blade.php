@php($editing = $member->exists)
<x-layout :title="$editing ? $member->name : __('Nuwe persoon')">
    <a class="back" href="{{ route('family.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Familie') }}</a>
    <h1>{{ $editing ? $member->name : ($member->kind === 'child' ? __('Nuwe kind') : __('Nuwe grootmens')) }}</h1>

    <form method="post" action="{{ $editing ? route('family.members.update', $member) : route('family.members.store') }}" class="stack" style="margin-top: 16px">
        @csrf
        @if ($editing) @method('put') @endif
        <label>{{ __('Naam') }}<input type="text" name="name" maxlength="100" required value="{{ old('name', $member->name) }}">
            @error('name')<span class="error">{{ $message }}</span>@enderror</label>
        <label>{{ __('Huis') }}
            <select name="household_id" required>
                @foreach ($households as $household)
                    <option value="{{ $household->id }}" @selected((int) old('household_id', $member->household_id) === $household->id)>{{ $household->name }}</option>
                @endforeach
            </select>
        </label>
        <fieldset>
            <legend>{{ __('Grootmens of kind') }}</legend>
            <div class="choice">
                <label><input type="radio" name="kind" value="adult" @checked(old('kind', $member->kind) === 'adult')><span>{{ __('Grootmens') }}</span></label>
                <label><input type="radio" name="kind" value="child" @checked(old('kind', $member->kind) === 'child')><span>{{ __('Kind') }}</span></label>
            </div>
        </fieldset>
        <label>{{ __('E-posadres') }} <span class="hint">{{ __('vir grootmense wat gaan aanmeld') }}</span>
            <input type="email" name="email" maxlength="255" value="{{ old('email', $member->email) }}">
            @error('email')<span class="error">{{ $message }}</span>@enderror</label>
        <label>{{ __('Verjaarsdag') }}<input type="date" name="birthday" value="{{ old('birthday', $member->birthday?->toDateString()) }}">
            @error('birthday')<span class="error">{{ $message }}</span>@enderror</label>
        <label class="check"><input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $member->is_admin))> {{ __('Beheerder (kan mense byvoeg en uitnodig)') }}</label>
        @error('is_admin')<span class="error">{{ $message }}</span>@enderror
        <div class="actions">
            <button type="submit">{{ $editing ? __('Stoor') : __('Voeg by') }}</button>
            <a class="button quiet" href="{{ route('family.index') }}">{{ __('Kanselleer') }}</a>
        </div>
    </form>

    @if ($editing && ! $member->is(auth()->user()))
        <form method="post" action="{{ route('family.members.destroy', $member) }}" data-confirm="{{ __('Verwyder :name uit Tribe? Hulle mylpale word ook verwyder; afsprake bly op die kalender.', ['name' => $member->name]) }}" style="margin-top: 28px">
            @csrf @method('delete')
            <button type="submit" class="danger">{{ __('Verwyder') }}</button>
        </form>
    @endif
</x-layout>
