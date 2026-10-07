<x-layout :title="__('Tik die kode in')">
    <div class="login">
        <p class="wordmark">Tribe</p>
        <h1>{{ __('Tik die kode in') }}</h1>
        <p class="muted">{{ __('As :email aan iemand in die familie behoort, is ’n kode nou op pad daarheen. Dit werk vir :minutes minute.', ['email' => $email, 'minutes' => \App\Models\LoginCode::VALID_MINUTES]) }}</p>

        <form method="post" action="{{ route('login.verify') }}" class="stack">
            @csrf
            <label>
                {{ __('Kode') }}
                <input type="text" name="code" class="code-input" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus>
                @error('code')<span class="error">{{ $message }}</span>@enderror
            </label>
            <button type="submit">{{ __('Meld aan') }}</button>
        </form>

        <p class="small"><a href="{{ route('login') }}">{{ __('Ander e-posadres, of stuur ’n nuwe kode') }}</a></p>
    </div>
</x-layout>
