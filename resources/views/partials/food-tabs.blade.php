<nav class="segments" aria-label="{{ __('Lyste en kos') }}">
    <a href="{{ route('lists.index') }}" @class(['is-active' => request()->routeIs('lists.*')]) @if (request()->routeIs('lists.*')) aria-current="page" @endif>{{ __('Lyste') }}</a>
    <a href="{{ route('meals.week') }}" @class(['is-active' => request()->routeIs('meals.week', 'meals.shopping')]) @if (request()->routeIs('meals.week', 'meals.shopping')) aria-current="page" @endif>{{ __('Weekmenu') }}</a>
    <a href="{{ route('meals.ideas') }}" @class(['is-active' => request()->routeIs('meals.ideas*')]) @if (request()->routeIs('meals.ideas*')) aria-current="page" @endif>{{ __('Idees') }}</a>
    <a href="{{ route('recipes.index') }}" @class(['is-active' => request()->routeIs('recipes.*')]) @if (request()->routeIs('recipes.*')) aria-current="page" @endif>{{ __('Resepte') }}</a>
</nav>
