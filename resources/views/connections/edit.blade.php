<x-layout :title="__('Kies ’n kalender')">
    <a class="back" href="{{ route('feeds.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Kalenders') }}</a>
    <h1>{{ $connection->providerName() }}</h1>
    @if ($connection->account)
        <p class="muted" style="margin-top: 6px">{{ $connection->account }}</p>
    @endif

    <form method="post" action="{{ route('connections.update', $connection) }}" class="stack" style="margin-top: 16px">
        @csrf @method('put')

        <fieldset>
            <legend>{{ __('Watter kalender?') }}</legend>
            @if ($calendars === [])
                <p class="empty">{{ __('Daar is geen kalender waarin Tribe mag skryf nie.') }}</p>
            @endif
            <div class="choice">
                @foreach ($calendars as $calendar)
                    <label><input type="radio" name="calendar" value="{{ $calendar['id'] }}" @checked(old('calendar', $connection->calendar_id) === $calendar['id'] || ($connection->calendar_id === null && $calendar['primary'])) required><span>{{ $calendar['name'] }}</span></label>
                @endforeach
            </div>
        </fieldset>

        <fieldset>
            <legend>{{ __('Wie sien wat daarvandaan in Tribe kom?') }}</legend>
            <div class="choice">
                <label><input type="radio" name="share" value="household" @checked(old('share', $connection->isFamilyWide() ? 'family' : 'household') === 'household')><span>{{ __('Net ons huis') }}</span></label>
                <label><input type="radio" name="share" value="family" @checked(old('share', $connection->isFamilyWide() ? 'family' : 'household') === 'family')><span>{{ __('Hele familie') }}</span></label>
            </div>
        </fieldset>

        <fieldset>
            <legend>{{ __('Wat uit Tribe gaan daarheen?') }}</legend>
            <div class="choice">
                <label><input type="radio" name="send" value="all" @checked(old('send', $connection->send) === 'all')><span>{{ __('Alles wat ek sien') }}</span></label>
                <label><input type="radio" name="send" value="ours" @checked(old('send', $connection->send) === 'ours')><span>{{ __('Net ons huis s’n') }}</span></label>
            </div>
        </fieldset>

        <p class="muted small">{{ __('Verjaarsdae kom nie saam nie; hulle is in die “net kyk”-kalender. Ingetekende kalenders (soos die skool s’n) ook nie.') }}</p>

        <div><button type="submit" @disabled($calendars === [])>{{ $connection->calendar_id ? __('Stoor') : __('Begin sinkroniseer') }}</button></div>
    </form>
</x-layout>
