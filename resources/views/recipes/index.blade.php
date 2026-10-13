<x-layout :title="__('Resepte')">
    @include('partials.food-tabs')

    <div class="head">
        <h1>{{ __('Resepte') }}</h1>
        <a class="button small" href="{{ route('recipes.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('Nuwe') }}</a>
    </div>

    <form method="get" action="{{ route('recipes.index') }}" class="search" role="search">
        <label class="visually-hidden" for="soek">{{ __('Soek resepte') }}</label>
        <input id="soek" type="search" name="soek" value="{{ $search }}" placeholder="{{ __('Soek op naam of bestanddeel') }}">
        <button type="submit" class="quiet">{{ __('Soek') }}</button>
    </form>

    @php($favourites = $search === '' ? $recipes->where('is_favourite', true) : collect())
    @if ($favourites->isNotEmpty())
        <h2>{{ __('Gunstelinge') }}</h2>
        @include('recipes.cards', ['recipes' => $favourites])
        <h2>{{ __('Al die resepte') }}</h2>
    @endif

    @if ($recipes->isEmpty())
        <p class="empty">
            @if ($search !== '')
                {{ __('Geen resepte met “:search” nie.', ['search' => $search]) }}
            @else
                {{ __('Nog geen resepte nie. Tik Ouma se melktert in, of plak ’n skakel na ’n resep op die internet.') }}
            @endif
        </p>
    @else
        @include('recipes.cards', ['recipes' => $recipes])
    @endif
</x-layout>
