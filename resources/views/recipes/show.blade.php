<x-layout :title="$recipe->title">
    <a class="back" href="{{ route('recipes.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Resepte') }}</a>
    <div class="head">
        <h1>{{ $recipe->title }}</h1>
        <a class="button quiet small" href="{{ route('recipes.edit', $recipe) }}">{{ __('Wysig') }}</a>
    </div>
    <p class="recipe-meta">
        @if ($recipe->servings)<span>{{ $recipe->servings }}</span>@endif
        @if ($recipe->time)<span>{{ $recipe->time }}</span>@endif
        <span>{{ $recipe->isFamilyWide() ? __('Hele familie') : __('Net ons huis') }}</span>
    </p>

    @if ($recipe->ingredientLines())
        <h2>{{ __('Bestanddele') }}</h2>
        @include('partials.to-list', ['lines' => $recipe->ingredientLines(), 'lists' => $lists])
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
