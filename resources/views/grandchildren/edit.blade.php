<x-layout :title="__('Wysig :name', ['name' => $child->name])">
    <a class="back" href="{{ route('grandchildren.show', $child) }}">@include('partials.icon', ['name' => 'back']) {{ $child->name }}</a>
    <h1>{{ $child->name }}</h1>

    <form method="post" action="{{ route('grandchildren.update', $child) }}" class="stack" style="margin-top: 16px">
        @csrf @method('put')
        <label>{{ __('Verjaarsdag') }}<input type="date" name="birthday" value="{{ old('birthday', $child->birthday?->toDateString()) }}">
            @error('birthday')<span class="error">{{ $message }}</span>@enderror</label>
        <div class="row">
            <label>{{ __('Klere-grootte') }}<input type="text" name="clothes_size" maxlength="50" value="{{ old('clothes_size', $child->clothes_size) }}" placeholder="{{ __('Bv. 7–8 jaar') }}"></label>
            <label>{{ __('Skoengrootte') }}<input type="text" name="shoe_size" maxlength="50" value="{{ old('shoe_size', $child->shoe_size) }}"></label>
        </div>
        <label>{{ __('Gunstelinge') }} <span class="hint">{{ __('kos, kleure, speelgoed, boeke') }}</span><textarea name="favourites" maxlength="2000">{{ old('favourites', $child->favourites) }}</textarea></label>
        <label>{{ __('Allergieë') }}<textarea name="allergies" maxlength="2000">{{ old('allergies', $child->allergies) }}</textarea></label>
        <label>{{ __('Wenslys') }} <span class="hint">{{ __('een ding per reël') }}</span><textarea name="wishlist" maxlength="2000">{{ old('wishlist', $child->wishlist) }}</textarea></label>
        <div class="actions">
            <button type="submit">{{ __('Stoor') }}</button>
            <a class="button quiet" href="{{ route('grandchildren.show', $child) }}">{{ __('Kanselleer') }}</a>
        </div>
    </form>
</x-layout>
