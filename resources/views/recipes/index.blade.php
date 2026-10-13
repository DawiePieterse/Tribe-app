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

    <ul class="cards">
        @forelse ($recipes as $recipe)
            <li>
                <a class="card" href="{{ route('recipes.show', $recipe) }}">
                    <span>
                        <strong>{{ $recipe->title }}</strong>
                        @if ($recipe->time || $recipe->servings)
                            <br><span class="muted small">{{ implode(' · ', array_filter([$recipe->time, $recipe->servings])) }}</span>
                        @endif
                    </span>
                </a>
            </li>
        @empty
            <li class="empty">
                @if ($search !== '')
                    {{ __('Geen resepte met “:search” nie.', ['search' => $search]) }}
                @else
                    {{ __('Nog geen resepte nie. Tik Ouma se melktert in, of plak ’n skakel na ’n resep op die internet.') }}
                @endif
            </li>
        @endforelse
    </ul>
</x-layout>
