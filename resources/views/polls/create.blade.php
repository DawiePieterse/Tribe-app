<x-layout :title="__('Vind ’n datum')">
    <a class="back" href="{{ route('polls.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Vind ’n datum') }}</a>
    <h1>{{ __('Vind ’n datum') }}</h1>

    <form method="post" action="{{ route('polls.store') }}" class="stack" style="margin-top: 16px">
        @csrf
        <label>{{ __('Wat beplan ons?') }}<input type="text" name="title" maxlength="255" required value="{{ old('title') }}" placeholder="{{ __('Bv. Kersfees by Oupa-hulle') }}">
            @error('title')<span class="error">{{ $message }}</span>@enderror</label>

        <fieldset>
            <legend>{{ __('Moontlike datums') }} <span class="hint">{{ __('tyd is opsioneel') }}</span></legend>
            <div class="stack" data-rows>
                @foreach (old('dates', array_fill(0, 3, '')) as $i => $date)
                    <div class="row" data-row>
                        <input type="date" name="dates[]" value="{{ $date }}" aria-label="{{ __('Datum') }}">
                        <input type="time" name="times[]" value="{{ old('times.'.$i) }}" aria-label="{{ __('Tyd') }}">
                    </div>
                @endforeach
            </div>
            <div><button type="button" class="quiet small" data-add-row>{{ __('+ Nog ’n datum') }}</button></div>
            @error('dates')<span class="error">{{ $message }}</span>@enderror
            @error('dates.*')<span class="error">{{ $message }}</span>@enderror
        </fieldset>

        <label>{{ __('Notas') }} <span class="hint">{{ __('opsioneel') }}</span><textarea name="notes" maxlength="2000">{{ old('notes') }}</textarea></label>
        <div class="actions">
            <button type="submit">{{ __('Vra die familie') }}</button>
            <a class="button quiet" href="{{ route('polls.index') }}">{{ __('Kanselleer') }}</a>
        </div>
    </form>
</x-layout>
