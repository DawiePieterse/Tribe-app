<x-layout :title="__('Meld aan')">
    <div class="login">
        <p class="wordmark">Tribe</p>
        <p class="muted">{{ __('Ons familie se kalender, lyste en planne.') }}</p>

        @if (session('logged_out'))
            <p class="flash" role="status">{{ __('Jy is afgemeld.') }}</p>
        @endif

        <form method="post" action="{{ route('login.send') }}" class="stack">
            @csrf
            <label>
                {{ __('Jou e-posadres') }}
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus>
                @error('email')<span class="error">{{ $message }}</span>@enderror
            </label>
            <button type="submit">{{ __('Stuur vir my ’n kode') }}</button>
            <p class="muted small">{{ __('Ons e-pos vir jou ’n kode van ses syfers. Daar is geen wagwoord nie.') }}</p>
        </form>
    </div>
</x-layout>
