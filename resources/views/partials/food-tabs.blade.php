<nav class="segments" aria-label="{{ __('Lyste en kos') }}">
    <a href="{{ route('lists.index') }}" @class(['is-active' => request()->routeIs('lists.*')]) @if (request()->routeIs('lists.*')) aria-current="page" @endif>{{ __('Lyste') }}</a>
    <a href="{{ route('meals.week') }}" @class(['is-active' => request()->routeIs('meals.*')]) @if (request()->routeIs('meals.*')) aria-current="page" @endif>{{ __('Weekmenu') }}</a>
    <a href="{{ route('recipes.index') }}" @class(['is-active' => request()->routeIs('recipes.*')]) @if (request()->routeIs('recipes.*')) aria-current="page" @endif>{{ __('Resepte') }}</a>
</nav>
