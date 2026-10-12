<x-layout :title="__('Kalenders')">
    <a class="back" href="{{ route('more') }}">@include('partials.icon', ['name' => 'back']) {{ __('Meer') }}</a>
    <h1>{{ __('Kalenders') }}</h1>

    <h2>{{ __('Tribe in jou iPhone-kalender') }}</h2>
    <p>{{ __('Sien alles uit Tribe tussen jou ander afsprake in die Kalender-app. Tribe werk dit elke uur by; verander dinge steeds hier in Tribe.') }}</p>
    <p><a class="button" href="{{ $webcal }}">@include('partials.icon', ['name' => 'calendar']) {{ __('Teken in op die kalender') }}</a></p>
    <p class="muted small">{{ __('Tik op die knoppie en dan op “Teken in”. Werk dit nie, kopieer die skakel en voeg dit by in Instellings › Kalender › Rekeninge › Voeg rekening by › Ander › Voeg ingetekende kalender by.') }}</p>
    <div class="copybox">
        <label class="visually-hidden" for="feed-link">{{ __('Jou kalenderskakel') }}</label>
        <input id="feed-link" type="text" value="{{ $feedUrl }}" readonly>
        <button type="button" class="quiet" data-copy="#feed-link" data-copied="{{ __('Gekopieer') }}">{{ __('Kopieer') }}</button>
    </div>
    <p class="muted small" style="margin-top: 8px">{{ __('Die skakel is net vir jou: wie dit het, kan sien wat jy in Tribe sien.') }}</p>
    <form method="post" action="{{ route('feeds.new-link') }}" data-confirm="{{ __('Maak ’n nuwe skakel? Die ou een hou op werk, en jy moet weer op jou toestelle inteken.') }}">
        @csrf
        <button type="submit" class="link small">{{ __('Maak ’n nuwe skakel') }}</button>
    </form>

    <h2>{{ __('Ander kalenders in Tribe') }}</h2>
    <p>{{ __('Teken in op ’n skool, sportklub of vakansiedae se kalender met hulle .ics- of webcal-skakel. Tribe kyk elke uur of iets verander het.') }}</p>

    <ul class="cards">
        @forelse ($feeds as $feed)
            <li class="card" style="--c: {{ $feed->colour ?? 'var(--action)' }}">
                <span>
                    <strong>{{ $feed->name }}</strong>
                    <br><span class="muted small">
                        {{ $feed->isFamilyWide() ? __('Hele familie') : __('Net ons huis') }} ·
                        {{ trans_choice(':count inskrywing|:count inskrywings', $feed->events_count) }}
                        @if ($feed->synced_at) · {{ __('bygewerk :when', ['when' => $feed->synced_at->diffForHumans()]) }}@endif
                    </span>
                    @if ($feed->last_error)
                        <br><span class="error">{{ $feed->last_error }}</span>
                    @endif
                </span>
                <span class="actions" style="justify-content: flex-end">
                    <form method="post" action="{{ route('feeds.sync', $feed) }}">
                        @csrf
                        <button type="submit" class="quiet small">{{ __('Werk by') }}</button>
                    </form>
                    <form method="post" action="{{ route('feeds.destroy', $feed) }}" data-confirm="{{ __('Verwyder “:name” en al sy inskrywings uit Tribe?', ['name' => $feed->name]) }}">
                        @csrf @method('delete')
                        <button type="submit" class="remove" aria-label="{{ __('Verwyder') }}">@include('partials.icon', ['name' => 'trash'])</button>
                    </form>
                </span>
            </li>
        @empty
            <li class="empty">{{ __('Nog geen ander kalenders nie.') }}</li>
        @endforelse
    </ul>

    <form method="post" action="{{ route('feeds.store') }}" class="stack" style="margin-top: 16px">
        @csrf
        <label>{{ __('Naam') }}<input type="text" name="name" maxlength="100" required value="{{ old('name') }}" placeholder="{{ __('Bv. Laerskool se kwartale') }}">
            @error('name')<span class="error">{{ $message }}</span>@enderror</label>
        <label>{{ __('Skakel') }}<input type="text" name="url" maxlength="1000" required value="{{ old('url') }}" inputmode="url" placeholder="webcal://… of https://….ics">
            @error('url')<span class="error">{{ $message }}</span>@enderror</label>
        <fieldset>
            <legend>{{ __('Wie sien dit?') }}</legend>
            <div class="choice">
                <label><input type="radio" name="share" value="household" @checked(old('share', 'household') === 'household')><span>{{ __('Net ons huis') }}</span></label>
                <label><input type="radio" name="share" value="family" @checked(old('share') === 'family')><span>{{ __('Hele familie') }}</span></label>
            </div>
        </fieldset>
        <div><button type="submit">{{ __('Teken in') }}</button></div>
    </form>
</x-layout>
