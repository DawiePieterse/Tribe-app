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
        <label class="check"><input type="checkbox" name="evening_email" value="1" @checked(old('evening_email', $me->evening_email))> {{ __('Stuur my elke aand ’n e-pos met môre se program (en my ritte)') }}</label>
        <div><button type="submit">{{ __('Stoor') }}</button></div>
    </form>

    @if ($push !== null)
        @push('scripts')<script src="/js/push.js?v={{ filemtime(public_path('js/push.js')) }}" defer></script>@endpush
        <h2>{{ __('Kennisgewings') }}</h2>
        <p>{{ __('Tribe laat weet jou op hierdie toestel: môre se afsprake, ’n nuwe byeenkoms of datumpeiling, wie se beurt dit is om te ry, en wanneer niemand nog ry nie.') }}</p>
        <div data-push data-push-key="{{ $push }}" data-push-url="{{ route('push.store') }}" hidden
             data-push-words="{{ json_encode([
                 'on' => __('Skakel kennisgewings aan'),
                 'off' => __('Skakel kennisgewings af'),
                 'turnedOn' => __('Aan. Jy kry kennisgewings op hierdie toestel.'),
                 'turnedOff' => __('Af op hierdie toestel.'),
                 'denied' => __('Jou toestel laat dit nie toe nie. Skakel dit aan by Instellings › Kennisgewings › Tribe.'),
                 'unsupported' => __('Op ’n iPhone werk dit net as Tribe op die Tuisskerm is: tik Deel in Safari, dan “Voeg by Tuisskerm”, en maak Tribe daarvandaan oop.'),
                 'failed' => __('Dit het nie gewerk nie. Probeer weer.'),
             ]) }}">
            <p><button type="button" data-push-toggle hidden></button></p>
            <p class="muted small" data-push-note></p>
        </div>
        <noscript><p class="muted small">{{ __('Kennisgewings het JavaScript nodig.') }}</p></noscript>
    @endif
</x-layout>
