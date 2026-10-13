@php($levels = [
    'quick' => [__('Vinnig'), __('tot 20 min')],
    'medium' => [__('Middelmatig'), __('20–40 min')],
    'long' => [__('Meer tyd'), __('40 min +')],
])
<x-layout :title="__('Kosidees')">
    @include('partials.food-tabs')

    <h1>{{ __('Wat maak ons vanaand?') }}</h1>
    <p class="muted" style="margin-top: 8px">{{ __('Tik twee of drie dinge wat julle het, kies hoeveel tyd daar is, en kry kos wat kinders eet.') }}</p>

    <form method="post" action="{{ route('ideas.suggest') }}" class="panel stack">
        @csrf
        <fieldset>
            <legend>{{ __('Wat het julle?') }}</legend>
            <datalist id="bekend">
                @foreach ($known as $name)
                    <option value="{{ $name }}"></option>
                @endforeach
            </datalist>
            <div class="have-inputs">
                @foreach ([__('hoender'), __('pasta'), __('kaas')] as $i => $placeholder)
                    <label class="visually-hidden" for="have{{ $i }}">{{ __('Bestanddeel :n', ['n' => $i + 1]) }}</label>
                    <input id="have{{ $i }}" type="text" name="have[]" list="bekend" maxlength="60" autocapitalize="off"
                           value="{{ old('have.'.$i, ($asked['pantry'] ?? false) ? '' : ($asked['have'][$i] ?? '')) }}" placeholder="{{ $placeholder }}">
                @endforeach
            </div>
            @error('have')<span class="error">{{ $message }}</span>@enderror
        </fieldset>

        @if ($bought->isNotEmpty())
            <fieldset>
                <legend>{{ __('Of kies uit wat julle gekoop het') }}</legend>
                <div class="choice">
                    @foreach ($bought as $name)
                        <label><input type="checkbox" name="bought[]" value="{{ $name }}" @checked(in_array($name, old('bought', []), true))><span>{{ $name }}</span></label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        <label class="people">
            {{ __('Vir hoeveel mense?') }} <span class="hint">{{ __('julle gewone getal; verander dit as daar gaste is') }}</span>
            <input type="number" name="people" min="1" max="{{ $maxPeople }}" inputmode="numeric" required value="{{ $people }}">
            @error('people')<span class="error">{{ $message }}</span>@enderror
        </label>
        <label class="check small"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> {{ __('Onthou dit as ons gewone getal') }}</label>

        <fieldset>
            <legend>{{ __('Hoeveel tyd is daar?') }}</legend>
            <div class="choice levels">
                @foreach ($levels as $value => [$label, $time])
                    <label><input type="radio" name="level" value="{{ $value }}" @checked(old('level', $asked['level'] ?? 'quick') === $value)><span><b>{{ $label }}</b> {{ $time }}</span></label>
                @endforeach
            </div>
        </fieldset>

        <div class="actions">
            <button type="submit" name="from" value="typed">{{ __('Wys idees') }}</button>
            @if ($bought->isNotEmpty())
                <button type="submit" name="from" value="bought" class="quiet">{{ __('Uit alles wat ons gekoop het') }}</button>
            @endif
        </div>
    </form>

    @if ($ideas !== [])
        <section id="idees">
            <h2>{{ ($asked['pantry'] ?? false) ? __('Uit wat julle gekoop het') : __('Met :have', ['have' => implode(', ', $asked['have'] ?? [])]) }}</h2>
            @if (($asked['source'] ?? '') === 'cookbook')
                <p class="muted small">{{ __('Uit Tribe se eie resepteboek.') }}</p>
            @endif

            @foreach ($ideas as $index => $idea)
                @php($missing = $idea->missing())
                @php($shareText = __('Inkopies vir :title', ['title' => $idea->title]).":\n".implode("\n", array_map(fn ($l) => '• '.$l, $missing)))
                <article class="idea">
                    <header>
                        <h3>{{ $idea->title }}</h3>
                        <p class="recipe-meta">
                            <span>{{ __(':n min', ['n' => $idea->minutes]) }}</span>
                            <span>{{ $levels[$idea->level()][0] }}</span>
                            <span>{{ trans_choice('vir :count mens|vir :count mense', $idea->people) }}</span>
                            <span>{{ $missing === [] ? __('Julle het alles') : trans_choice(':count ding om te koop|:count dinge om te koop', count($missing)) }}</span>
                        </p>
                        @if ($idea->kids !== '')
                            <p class="kids">{{ $idea->kids }}</p>
                        @endif
                    </header>

                    <details>
                        <summary>{{ __('Bestanddele en metode') }}</summary>
                        <ul class="ingredients">
                            @foreach ($idea->ingredients as $ingredient)
                                <li><span>{{ $ingredient['line'] }}</span>@unless ($ingredient['have'])<span class="tag-buy">{{ __('koop') }}</span>@endunless</li>
                            @endforeach
                        </ul>
                        <ol class="method">
                            @foreach ($idea->method as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                    </details>

                    <div class="actions">
                        <form method="post" action="{{ route('ideas.week', $index) }}" class="actions">
                            @csrf
                            <label class="visually-hidden" for="date{{ $index }}">{{ __('Watter dag?') }}</label>
                            <select id="date{{ $index }}" name="date">
                                @foreach ($days as $day)
                                    <option value="{{ $day->toDateString() }}">{{ $loop->first ? __('Vanaand') : $day->translatedFormat('l j M') }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="small">{{ __('Op die weekmenu') }}</button>
                        </form>
                        <form method="post" action="{{ route('ideas.recipe', $index) }}">
                            @csrf
                            <button type="submit" class="quiet small">{{ __('Stoor resep') }}</button>
                        </form>
                    </div>

                    @if ($missing !== [])
                        <details>
                            <summary>{{ __('Koop wat kort') }}</summary>
                            @include('partials.to-list', ['lines' => $missing, 'lists' => $lists])
                            <p>
                                <x-message class="button quiet small" :text="$shareText" />
                            </p>
                            <p class="muted small">{{ __('Sixty60 kan nie ’n lys invoer nie: tik “Checkers” by ’n item om dit daar te soek.') }}</p>
                        </details>
                    @endif
                </article>
            @endforeach
        </section>
    @endif

    <h2>{{ __('Wat het julle gekoop?') }}</h2>
    <p>{{ __('Laat Tribe weet wat in die kas is, dan stel dit kos voor uit wat julle reeds het.') }}</p>

    @if ($claude)
        <form method="post" action="{{ route('ideas.bought') }}" enctype="multipart/form-data" class="panel stack">
            @csrf
            <label>{{ __('Foto van die strokie of Sixty60-bestelling') }}
                <input type="file" name="slip" accept="image/*,application/pdf" required data-shrink>
                <span class="hint">{{ __('Sixty60: maak die bestelling oop onder Profiel › Bestellings en neem ’n skermskoot, of vra die BTW-faktuur en laai die PDF op.') }}</span>
            </label>
            @error('slip')<span class="error">{{ $message }}</span>@enderror
            <div><button type="submit">{{ __('Lees wat ons gekoop het') }}</button></div>
        </form>
    @endif

    <form method="post" action="{{ route('ideas.bought') }}" class="stack" @if ($claude) style="margin-top: 4px" @endif>
        @csrf
        <details @if (! $claude) open @endif>
            <summary class="link">{{ $claude ? __('Of plak die lys as teks') : __('Plak die strokie of bestelling as teks') }}</summary>
            <div class="stack" style="margin-top: 10px">
                <label class="visually-hidden" for="bought-text">{{ __('Wat julle gekoop het') }}</label>
                <textarea id="bought-text" name="text" maxlength="20000" placeholder="{{ __('Plak die items uit die Sixty60-e-pos of faktuur, of tik een per reël') }}">{{ old('text') }}</textarea>
                @unless ($claude)
                    @error('slip')<span class="error">{{ $message }}</span>@enderror
                @endunless
                <div><button type="submit" class="quiet">{{ __('Lees die lys') }}</button></div>
            </div>
        </details>
    </form>

    @if ($bought->isNotEmpty())
        <p class="muted small" style="margin-top: 14px">{{ __('Tribe onthou :days dae lank wat gekoop is:', ['days' => \App\Models\Grocery::KEEP_DAYS]) }} {{ $bought->implode(', ') }}.</p>
        <form method="post" action="{{ route('ideas.forget') }}" data-confirm="{{ __('Vergeet alles wat julle gekoop het?') }}">
            @csrf @method('delete')
            <button type="submit" class="link small">{{ __('Vergeet dit') }}</button>
        </form>
    @endif
</x-layout>
