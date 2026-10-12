@php
    $editing = $recipe->exists;
    $share = old('share', $editing && $recipe->household_id !== null ? 'household' : 'family');
@endphp
<x-layout :title="$editing ? $recipe->title : __('Nuwe resep')">
    <a class="back" href="{{ $editing ? route('recipes.show', $recipe) : route('recipes.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Resepte') }}</a>
    <h1>{{ $editing ? __('Wysig resep') : __('Nuwe resep') }}</h1>

    @unless ($editing || ! empty($imported))
        <form method="post" action="{{ route('recipes.import') }}" class="panel stack" style="margin-top: 16px">
            @csrf
            <label>
                {{ __('Het jy ’n skakel na die resep?') }}
                <span class="hint">{{ __('Plak dit hier, dan vul Tribe die res in.') }}</span>
                <input type="url" name="url" value="{{ old('url') }}" placeholder="https://" inputmode="url">
                @error('url')<span class="error">{{ $message }}</span>@enderror
            </label>
            <div><button type="submit" class="quiet">{{ __('Haal die resep') }}</button></div>
        </form>
    @endunless

    @if (! empty($imported))
        <p class="flash" role="status">{{ __('Die resep is ingelees. Kyk of alles reg is en stoor dit dan.') }}</p>
    @endif

    <form method="post" action="{{ $editing ? route('recipes.update', $recipe) : route('recipes.store') }}" class="stack" style="margin-top: 16px">
        @csrf
        @if ($editing) @method('put') @endif
        <label>{{ __('Naam') }}<input type="text" name="title" maxlength="255" required value="{{ old('title', $recipe->title) }}" placeholder="{{ __('Bv. Ouma se melktert') }}">
            @error('title')<span class="error">{{ $message }}</span>@enderror</label>
        <div class="row">
            <label>{{ __('Porsies') }}<input type="text" name="servings" maxlength="50" value="{{ old('servings', $recipe->servings) }}" placeholder="{{ __('Bv. 6 porsies') }}"></label>
            <label>{{ __('Tyd') }}<input type="text" name="time" maxlength="50" value="{{ old('time', $recipe->time) }}" placeholder="{{ __('Bv. 1 uur') }}"></label>
        </div>
        <label>{{ __('Bestanddele') }} <span class="hint">{{ __('een per reël') }}</span>
            <textarea name="ingredients" rows="8" maxlength="10000">{{ old('ingredients', $recipe->ingredients) }}</textarea></label>
        <label>{{ __('Metode') }} <span class="hint">{{ __('een stap per reël') }}</span>
            <textarea name="method" rows="8" maxlength="20000">{{ old('method', $recipe->method) }}</textarea></label>
        <label>{{ __('Notas') }}<textarea name="notes" maxlength="5000">{{ old('notes', $recipe->notes) }}</textarea></label>
        <input type="hidden" name="source_url" value="{{ old('source_url', $recipe->source_url) }}">
        <fieldset>
            <legend>{{ __('Wie sien dit?') }}</legend>
            <div class="choice">
                <label><input type="radio" name="share" value="family" @checked($share === 'family')><span>{{ __('Hele familie') }}</span></label>
                <label><input type="radio" name="share" value="household" @checked($share === 'household')><span>{{ __('Net ons huis') }}</span></label>
            </div>
        </fieldset>
        <div class="actions">
            <button type="submit">{{ $editing ? __('Stoor') : __('Stoor resep') }}</button>
            <a class="button quiet" href="{{ $editing ? route('recipes.show', $recipe) : route('recipes.index') }}">{{ __('Kanselleer') }}</a>
        </div>
    </form>

    @if ($editing)
        <form method="post" action="{{ route('recipes.destroy', $recipe) }}" data-confirm="{{ __('Verwyder die resep “:title”?', ['title' => $recipe->title]) }}" style="margin-top: 28px">
            @csrf @method('delete')
            <button type="submit" class="danger">{{ __('Verwyder') }}</button>
        </form>
    @endif
</x-layout>
