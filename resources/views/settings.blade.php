<x-layout :title="__('My instellings')">
    <a class="back" href="{{ route('more') }}">@include('partials.icon', ['name' => 'back']) {{ __('Meer') }}</a>
    <h1>{{ __('My instellings') }}</h1>

    <form method="post" action="{{ route('settings.update') }}" class="stack" style="margin-top: 16px">
        @csrf @method('put')
        <label>{{ __('Naam') }}<input type="text" name="name" maxlength="100" required value="{{ old('name', $me->name) }}">
            @error('name')<span class="error">{{ $message }}</span>@enderror</label>
        <label>{{ __('E-posadres') }} <span class="hint">{{ __('jou aanmeldkodes gaan hierheen') }}</span>
            <input type="email" name="email" maxlength="255" required value="{{ old('email', $me->email) }}">
            @error('email')<span class="error">{{ $message }}</span>@enderror</label>
        <label>{{ __('Verjaarsdag') }}<input type="date" name="birthday" value="{{ old('birthday', $me->birthday?->toDateString()) }}"></label>
        <label class="check"><input type="checkbox" name="large_text" value="1" @checked(old('large_text', $me->large_text))> {{ __('Groter teks') }}</label>
        <div><button type="submit">{{ __('Stoor') }}</button></div>
    </form>
</x-layout>
