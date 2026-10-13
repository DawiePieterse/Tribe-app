<x-layout :title="__('Plak ’n boodskap')">
    <a class="back" href="{{ route('calendar') }}">@include('partials.icon', ['name' => 'back']) {{ __('Kalender') }}</a>
    <h1>{{ __('Plak ’n boodskap') }}</h1>
    <p class="muted">{{ __('Kopieer ’n plan uit die familie se Messages-groep en plak dit hier. Tribe vul die datum, tyd en plek vir jou in; jy kyk dit na voor dit op die kalender gaan.') }}</p>

    <form method="post" action="{{ route('share.read') }}" class="stack" style="margin-top: 16px">
        @csrf
        <label>
            <span class="visually-hidden">{{ __('Boodskap') }}</span>
            <textarea name="text" rows="6" maxlength="4000" required autofocus placeholder="{{ __('Bv. Ouma nooi almal vir middagete Sondag 12:30, bring asseblief ’n slaai') }}">{{ old('text') }}</textarea>
            @error('text')<span class="error">{{ $message }}</span>@enderror
        </label>
        <div><button type="submit">{{ __('Lees die boodskap') }}</button></div>
        @if ($usesClaude)
            <p class="muted small">{{ __('Net hierdie boodskap word na Claude (Anthropic) gestuur om dit te lees.') }}</p>
        @endif
    </form>

    <p class="small" style="margin-top: 24px"><a href="{{ route('share.setup') }}">{{ __('Stuur boodskappe reguit uit Messages na Tribe') }}</a></p>
</x-layout>
