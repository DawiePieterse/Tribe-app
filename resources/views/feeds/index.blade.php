<x-layout :title="__('Kalenders')">
    <a class="back" href="{{ route('more') }}">@include('partials.icon', ['name' => 'back']) {{ __('Meer') }}</a>
    <h1>{{ __('Kalenders') }}</h1>

    <h2>{{ __('Jou eie kalender, albei kante') }}</h2>
    <p>{{ __('Koppel jou Google-, Outlook- of iCloud-kalender. Wat jy daar byvoeg of verander, kom in Tribe; wat in Tribe gebeur, kom in jou kalender. Tribe sinkroniseer elke tien minute en dadelik ná ’n verandering hier.') }}</p>

    @if ($connections->isNotEmpty())
        <ul class="cards">
            @foreach ($connections as $connection)
                <li class="card" style="--c: var(--action)">
                    <span>
                        <strong>{{ $connection->calendar_name ?? $connection->providerName() }}</strong>
                        <br><span class="muted small">
                            {{ $connection->providerName() }}@if ($connection->account) · {{ $connection->account }}@endif
                            @if ($connection->synced_at) · {{ __('gesinkroniseer :when', ['when' => $connection->synced_at->diffForHumans()]) }}@endif
                        </span>
                        @if ($connection->calendar_id === null)
                            <br><a class="small" href="{{ route('connections.edit', $connection) }}">{{ __('Kies watter kalender') }}</a>
                        @endif
                        @if ($connection->last_error)
                            <br><span class="error">{{ $connection->last_error }}</span>
                        @endif
                    </span>
                    <span class="actions" style="justify-content: flex-end">
                        @if ($connection->calendar_id !== null)
                            <a class="button quiet small" href="{{ route('connections.edit', $connection) }}">{{ __('Stel') }}</a>
                            <form method="post" action="{{ route('connections.sync', $connection) }}">
                                @csrf
                                <button type="submit" class="quiet small">{{ __('Nou') }}</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('connections.destroy', $connection) }}" data-confirm="{{ __('Ontkoppel :provider? Wat uit daardie kalender in Tribe gekom het, word uit Tribe verwyder; Tribe se inskrywings bly in jou kalender.', ['provider' => $connection->providerName()]) }}">
                            @csrf @method('delete')
                            <button type="submit" class="remove" aria-label="{{ __('Ontkoppel') }}">@include('partials.icon', ['name' => 'trash'])</button>
                        </form>
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="actions" style="margin-top: 14px">
        @foreach ($providers as $provider)
            <a class="button {{ $loop->first ? '' : 'quiet' }}" href="{{ $provider === 'icloud' ? route('connections.icloud') : route('connections.connect', $provider) }}">
                @include('partials.icon', ['name' => 'plus']) {{ __('Koppel :provider', ['provider' => \App\Models\CalendarConnection::label($provider)]) }}
            </a>
        @endforeach
    </div>

    <h2>{{ __('Of net kyk: Tribe in jou iPhone-kalender') }}</h2>
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
