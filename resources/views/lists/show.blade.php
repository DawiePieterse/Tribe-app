<x-layout :title="$list->name">
    @push('scripts')
        <script src="/js/lists.js?v={{ filemtime(public_path('js/lists.js')) }}" defer></script>
    @endpush

    <a class="back" href="{{ route('lists.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Lyste') }}</a>
    <div class="head">
        <div>
            <h1>{{ $list->name }}</h1>
            <p class="muted small">{{ $list->isFamilyWide() ? __('Hele familie') : __('Net ons huis') }}</p>
        </div>
    </div>

    <p class="offline-note">{{ __('Geen sein nie. Wat jy afmerk of byvoeg, word gestoor sodra daar weer sein is.') }}</p>

    <div data-list="{{ $list->id }}"
         data-add-url="{{ route('items.store', $list) }}"
         data-pending-text="{{ __('Word gestoor sodra daar sein is') }}">
        <form method="post" action="{{ route('items.store', $list) }}" data-add-form>
            @csrf
            <div class="add-item">
                <label class="visually-hidden" for="new-item">{{ __('Nuwe item') }}</label>
                <input id="new-item" type="text" name="title" maxlength="255" required autocomplete="off"
                       placeholder="{{ $list->isShopping() ? __('Bv. melk, brood') : __('Wat moet gedoen word?') }}">
                <button type="submit" class="round" aria-label="{{ __('Voeg by') }}">@include('partials.icon', ['name' => 'plus'])</button>
            </div>
            @unless ($list->isShopping())
                <div class="row" style="margin: -6px 0 18px">
                    <label class="small">{{ __('Vir wie') }}
                        <select name="assigned_member_id">
                            <option value="">{{ __('Enigiemand') }}</option>
                            @foreach ($people as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="small">{{ __('Teen wanneer') }}<input type="date" name="due_on"></label>
                </div>
            @endunless
        </form>

        <ul class="items" data-open>
            @foreach ($open as $item)
                @include('lists.item', ['item' => $item])
            @endforeach
        </ul>
        @if ($open->isEmpty())
            <p class="empty" data-empty>{{ $list->isShopping() ? __('Alles is gekoop.') : __('Alles is gedoen.') }}</p>
        @endif

        @if ($done->isNotEmpty())
            <h2>{{ __('Klaar') }}</h2>
            <ul class="items" data-done>
                @foreach ($done as $item)
                    @include('lists.item', ['item' => $item])
                @endforeach
            </ul>
            <form method="post" action="{{ route('lists.clear', $list) }}" style="margin-top: 12px">
                @csrf
                <button type="submit" class="quiet small">{{ __('Haal afgemerkte items weg') }}</button>
            </form>
        @endif
    </div>

    <details style="margin-top: 32px">
        <summary>{{ __('Lys se instellings') }}</summary>
        <form method="post" action="{{ route('lists.update', $list) }}" class="stack" style="margin-top: 12px">
            @csrf @method('put')
            <label>{{ __('Naam') }}<input type="text" name="name" value="{{ $list->name }}" maxlength="100" required></label>
            <input type="hidden" name="kind" value="{{ $list->kind }}">
            <fieldset>
                <legend>{{ __('Wie sien dit?') }}</legend>
                <div class="choice">
                    <label><input type="radio" name="share" value="household" @checked(! $list->isFamilyWide())><span>{{ __('Net ons huis') }}</span></label>
                    <label><input type="radio" name="share" value="family" @checked($list->isFamilyWide())><span>{{ __('Hele familie') }}</span></label>
                </div>
            </fieldset>
            <div><button type="submit">{{ __('Stoor') }}</button></div>
        </form>
        <form method="post" action="{{ route('lists.destroy', $list) }}" data-confirm="{{ __('Verwyder die hele lys “:name”?', ['name' => $list->name]) }}" style="margin-top: 16px">
            @csrf @method('delete')
            <button type="submit" class="danger">{{ __('Verwyder die lys') }}</button>
        </form>
    </details>
</x-layout>
