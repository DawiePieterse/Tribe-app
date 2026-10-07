<x-layout :title="__('Welkom')">
    <div class="login">
        <p class="wordmark">Tribe</p>
        <h1>{{ __('Welkom, :name', ['name' => $member->firstName()]) }}</h1>
        <p>{{ __('Jy is aangemeld. Sit Tribe nou op jou tuisskerm, dan werk dit soos ’n gewone app:') }}</p>
        <ol>
            <li>{{ __('Tik op die Deel-knoppie onder in Safari (die blokkie met ’n pyltjie).') }}</li>
            <li>{{ __('Kies “Voeg by tuisskerm” en tik “Voeg by”.') }}</li>
            <li>{{ __('Maak Tribe van jou tuisskerm oop. As dit vra, meld een keer aan met jou e-posadres.') }}</li>
        </ol>
        <p class="muted small">{{ __('Die app op die tuisskerm onthou jou daarna; jy hoef nie weer aan te meld nie.') }}</p>
        <p><a class="button" href="{{ route('home') }}">{{ __('Gaan na Tribe') }}</a></p>
    </div>
</x-layout>
