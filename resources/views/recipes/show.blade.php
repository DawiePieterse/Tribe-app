<x-layout :title="$recipe->title">
    <a class="back" href="{{ route('recipes.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Resepte') }}</a>
    <div class="head">
        <h1>{{ $recipe->title }}</h1>
        <span class="actions">
            <form method="post" action="{{ route('recipes.favourite', $recipe) }}">
                @csrf
                <button type="submit" @class(['fav', 'is-on' => $favourite]) aria-pressed="{{ $favourite ? 'true' : 'false' }}" aria-label="{{ $favourite ? __('Haal uit my gunstelinge') : __('Merk as gunsteling') }}">
                    @include('partials.icon', ['name' => $favourite ? 'heart-filled' : 'heart'])
                </button>
            </form>
            <a class="button quiet small" href="{{ route('recipes.edit', $recipe) }}">{{ __('Wysig') }}</a>
        </span>
    </div>
    <p class="recipe-meta">
        @if ($recipe->servings)<span>{{ $recipe->servings }}</span>@endif
        @if ($recipe->time)<span>{{ $recipe->time }}</span>@endif
        <span>{{ $recipe->isFamilyWide() ? __('Hele familie') : __('Net ons huis') }}</span>
    </p>

    @if ($lines)
        <div class="head">
            <h2>{{ __('Bestanddele') }}</h2>
            @if ($people !== null)
                <form method="get" action="{{ route('recipes.show', $recipe) }}" class="actions for-people">
                    <label for="vir" class="small muted">{{ __('vir') }}</label>
                    <input id="vir" type="number" name="vir" min="1" max="{{ $maxPeople }}" inputmode="numeric" value="{{ $people }}" data-autosubmit aria-label="{{ __('Vir hoeveel mense') }}">
                    <span class="small muted">{{ trans_choice('mens|mense', $people) }}</span>
                    <noscript><button type="submit" class="quiet small">{{ __('Wys') }}</button></noscript>
                </form>
            @endif
        </div>
        @if ($people !== null && $people !== $recipe->servingsCount())
            <p class="muted small">{{ __('Die resep is vir :written geskryf; die hoeveelhede is vir :people aangepas.', ['written' => $recipe->servingsCount(), 'people' => $people]) }}</p>
        @endif
        @include('partials.to-list', ['lines' => $lines, 'lists' => $lists])
    @endif

    @if ($recipe->methodSteps())
        <h2>{{ __('Metode') }}</h2>
        <ol class="method">
            @foreach ($recipe->methodSteps() as $step)
                <li>{{ $step }}</li>
            @endforeach
        </ol>
    @endif

    @if ($recipe->notes)
        <h2>{{ __('Notas') }}</h2>
        <p class="notes">{{ $recipe->notes }}</p>
    @endif

    @if ($recipe->source_url)
        <p class="small muted">{{ __('Bron:') }} <a href="{{ $recipe->source_url }}" rel="noopener noreferrer" target="_blank">{{ parse_url($recipe->source_url, PHP_URL_HOST) }}</a></p>
    @endif
</x-layout>
