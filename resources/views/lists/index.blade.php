<x-layout :title="__('Lyste')">
    <h1>{{ __('Lyste') }}</h1>

    <h2>{{ __('Ons huis') }}</h2>
    <ul class="cards">
        @forelse ($ours as $list)
            <li>
                <a class="card" href="{{ route('lists.show', $list) }}" style="--c: {{ auth()->user()->household->colour }}">
                    <strong>{{ $list->name }}</strong>
                    <span class="badge">{{ $list->open_count }}</span>
                </a>
            </li>
        @empty
            <li class="empty">{{ __('Nog geen lyste vir julle huis nie.') }}</li>
        @endforelse
    </ul>

    <h2>{{ __('Hele familie') }}</h2>
    <ul class="cards">
        @forelse ($family as $list)
            <li>
                <a class="card" href="{{ route('lists.show', $list) }}">
                    <strong>{{ $list->name }}</strong>
                    <span class="badge">{{ $list->open_count }}</span>
                </a>
            </li>
        @empty
            <li class="empty">{{ __('Nog geen familielyste nie, soos ’n paklys vir die vakansie.') }}</li>
        @endforelse
    </ul>

    <h2>{{ __('Nuwe lys') }}</h2>
    <form method="post" action="{{ route('lists.store') }}" class="stack">
        @csrf
        <label>
            {{ __('Naam') }}
            <input type="text" name="name" maxlength="100" required placeholder="{{ __('Bv. Paklys vir Desember') }}" value="{{ old('name') }}">
            @error('name')<span class="error">{{ $message }}</span>@enderror
        </label>
        <fieldset>
            <legend>{{ __('Soort') }}</legend>
            <div class="choice">
                <label><input type="radio" name="kind" value="shopping" @checked(old('kind', 'shopping') === 'shopping')><span>{{ __('Inkopies') }}</span></label>
                <label><input type="radio" name="kind" value="tasks" @checked(old('kind') === 'tasks')><span>{{ __('Doenlys') }}</span></label>
            </div>
        </fieldset>
        <fieldset>
            <legend>{{ __('Wie sien dit?') }}</legend>
            <div class="choice">
                <label><input type="radio" name="share" value="household" @checked(old('share', 'household') === 'household')><span>{{ __('Net ons huis') }}</span></label>
                <label><input type="radio" name="share" value="family" @checked(old('share') === 'family')><span>{{ __('Hele familie') }}</span></label>
            </div>
        </fieldset>
        <div><button type="submit">{{ __('Maak die lys') }}</button></div>
    </form>
</x-layout>
