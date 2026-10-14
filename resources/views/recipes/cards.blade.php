{{-- $recipes: a list of recipes with is_favourite set (Recipe::withFavourite). --}}
<ul class="cards">
    @foreach ($recipes as $recipe)
        <li>
            <a class="card" href="{{ route('recipes.show', $recipe) }}">
                <span>
                    @if ($recipe->is_favourite)<span class="fav-mark" aria-label="{{ __('Gunsteling') }}">@include('partials.icon', ['name' => 'heart'])</span>@endif
                    <strong>{{ $recipe->title }}</strong>
                    @if ($recipe->time || $recipe->servings)
                        <br><span class="muted small">{{ implode(' · ', array_filter([$recipe->time, $recipe->servings])) }}</span>
                    @endif
                </span>
            </a>
        </li>
    @endforeach
</ul>
