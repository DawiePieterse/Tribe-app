<x-layout :title="__('Koppel iCloud')">
    <a class="back" href="{{ route('feeds.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Kalenders') }}</a>
    <h1>{{ __('Koppel iCloud') }}</h1>
    <p class="muted" style="margin-top: 8px">{{ __('Apple laat Tribe toe met ’n app-wagwoord, nie jou gewone Apple ID-wagwoord nie.') }}</p>

    <ol class="steps">
        <li>{{ __('Gaan na appleid.apple.com en meld aan.') }}</li>
        <li>{{ __('Kies Aanmelding en sekuriteit › App-spesifieke wagwoorde, en maak een met die naam “Tribe”.') }}</li>
        <li>{{ __('Kopieer die wagwoord (xxxx-xxxx-xxxx-xxxx) en plak dit hier onder.') }}</li>
    </ol>

    <form method="post" action="{{ route('connections.icloud.store') }}" class="stack" style="margin-top: 16px">
        @csrf
        <label>{{ __('Apple ID') }}
            <input type="email" name="apple_id" value="{{ old('apple_id', auth()->user()->email) }}" autocomplete="username" inputmode="email" required>
            @error('apple_id')<span class="error">{{ $message }}</span>@enderror
        </label>
        <label>{{ __('App-wagwoord') }}
            <input type="text" name="password" required autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="xxxx-xxxx-xxxx-xxxx">
            @error('password')<span class="error">{{ $message }}</span>@enderror
        </label>
        <p class="muted small">{{ __('Tribe bewaar dit geënkripteer en gebruik dit net vir jou kalender. Jy kan dit enige tyd by Apple intrek.') }}</p>
        <div><button type="submit">{{ __('Koppel') }}</button></div>
    </form>
</x-layout>
