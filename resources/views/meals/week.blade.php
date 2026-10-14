@php($slots = ['breakfast' => __('Ontbyt'), 'lunch' => __('Middagete'), 'dinner' => __('Aandete')])
<x-layout :title="__('Weekmenu')">
    @include('partials.food-tabs')

    @include('partials.week-nav', [
        'previous' => route('meals.week', ['week' => $weekStart->subWeek()->toDateString()]),
        'next' => route('meals.week', ['week' => $weekStart->addWeek()->toDateString()]),
    ])

    <p class="actions">
        <a class="button" href="{{ route('ideas.index') }}">{{ __('Wat maak ons vanaand?') }}</a>
        <a class="button quiet" href="{{ route('meals.shopping', ['week' => $weekStart->toDateString()]) }}">@include('partials.icon', ['name' => 'list']) {{ __('Inkopies vir die week') }}</a>
        @if ($meals->isNotEmpty())
            <x-message :title="__('Weekmenu :week', ['week' => \App\Support\Dates::weekRange($weekStart)])" :lines="$menuLines" :label="__('Stuur')" />
        @endif
    </p>

    <ol class="week">
        @for ($i = 0; $i < 7; $i++)
            @php($day = $weekStart->addDays($i))
            @php($dayMeals = $meals->get($day->toDateString(), collect()))
            <li id="d{{ $day->toDateString() }}" @class(['is-today' => $day->isSameDay($today)])>
                <div class="date" aria-hidden="true">
                    <b>{{ $day->day }}</b>
                    <span>{{ __(['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Sa', 'So'][$i]) }}</span>
                </div>
                <div>
                    <h2 class="visually-hidden">{{ $day->translatedFormat('l j F') }}</h2>
                    @if ($dayMeals->isNotEmpty())
                        <ul class="meals">
                            @foreach ($dayMeals as $meal)
                                <li class="meal">
                                    <span>
                                        @if ($meal->slot !== 'dinner')<span class="slot">{{ $slots[$meal->slot] }}</span>@endif
                                        @if ($meal->recipe)
                                            <a href="{{ route('recipes.show', $meal->recipe) }}">{{ $meal->label() }}</a>
                                        @else
                                            {{ $meal->label() }}
                                        @endif
                                    </span>
                                    <form method="post" action="{{ route('meals.destroy', $meal) }}">
                                        @csrf @method('delete')
                                        <button type="submit" class="remove" aria-label="{{ __('Haal “:title” af', ['title' => $meal->label()]) }}">@include('partials.icon', ['name' => 'trash'])</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <details class="add-meal">
                        <summary>{{ $dayMeals->isEmpty() ? __('Wat eet ons?') : __('Nog iets') }}</summary>
                        <form method="post" action="{{ route('meals.store') }}">
                            @csrf
                            <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                            <select name="recipe_id" aria-label="{{ __('Resep') }}">
                                <option value="">{{ __('Kies ’n resep…') }}</option>
                                @foreach ($recipes as $recipe)
                                    <option value="{{ $recipe->id }}">{{ $recipe->is_favourite ? '♥ ' : '' }}{{ $recipe->title }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="title" maxlength="255" placeholder="{{ __('of tik iets, bv. braai') }}" aria-label="{{ __('Of tik wat julle eet') }}">
                            <div class="actions">
                                <select name="slot" aria-label="{{ __('Watter ete') }}" style="flex: 1">
                                    @foreach ($slots as $value => $label)
                                        <option value="{{ $value }}" @selected($value === 'dinner')>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="small">{{ __('Voeg by') }}</button>
                            </div>
                        </form>
                    </details>
                </div>
            </li>
        @endfor
    </ol>
    @error('title')<p class="error">{{ $message }}</p>@enderror
</x-layout>
