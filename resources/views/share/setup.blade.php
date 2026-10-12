<x-layout :title="__('Stuur na Tribe')">
    <a class="back" href="{{ route('more') }}">@include('partials.icon', ['name' => 'back']) {{ __('Meer') }}</a>
    <h1>{{ __('Stuur na Tribe') }}</h1>
    <p>{{ __('Stuur ’n plan uit die familie se WhatsApp-groep reguit na Tribe. Dit wag dan op jou tuisblad; jy tik daarop, kyk die datum na en stoor.') }}</p>
    <p class="muted small">
        {{ __('Net boodskappe wat jy self stuur, gaan na Tribe; Tribe lees nooit die groep nie.') }}
        @if ($usesClaude) {{ __('Die boodskap word na Claude (Anthropic) gestuur om die datum uit te lees.') }} @endif
    </p>
    <p><a class="button quiet" href="{{ route('share.paste') }}">{{ __('Of plak ’n boodskap in Tribe') }}</a></p>

    <h2>{{ __('1. Jou sleutel') }}</h2>
    @if ($key)
        <div class="keybox">
            <p><strong>{{ __('Kopieer die sleutel nou: Tribe wys dit net hierdie een keer.') }}</strong></p>
            <div class="copybox">
                <label class="visually-hidden" for="share-key">{{ __('Sleutel') }}</label>
                <input id="share-key" type="text" value="Bearer {{ $key }}" readonly>
                <button type="button" class="quiet" data-copy="#share-key" data-copied="{{ __('Gekopieer') }}">{{ __('Kopieer') }}</button>
            </div>
        </div>
    @else
        <p>{{ $me->share_token_hash ? __('Jy het reeds ’n sleutel. Maak net ’n nuwe een as jy die opdrag oor moet opstel; die ou een hou dan op werk.') : __('Maak eers jou sleutel. Dit laat jou opdrag toe om boodskappe na jou Tribe te stuur.') }}</p>
        <form method="post" action="{{ route('share.key') }}" @if ($me->share_token_hash) data-confirm="{{ __('Maak ’n nuwe sleutel? Die ou een hou op werk.') }}" @endif>
            @csrf
            <button type="submit">{{ __('Maak my sleutel') }}</button>
        </form>
    @endif

    <h2>{{ __('2. Maak die opdrag op jou iPhone') }}</h2>
    <p class="muted small">{{ __('Die name in aanhalingstekens is soos jou iPhone dit in Engels wys. Dit neem ’n paar minute, net een keer.') }}</p>
    <ol class="steps">
        <li>{{ __('Maak die “Shortcuts”-app oop en tik op + vir ’n nuwe opdrag. Noem dit “Stuur na Tribe”.') }}</li>
        <li>{{ __('Tik op die ⓘ-knoppie onder en skakel “Show in Share Sheet” aan. Kies by “Receive” net “Text”.') }}</li>
        <li>{{ __('Voeg die aksie “Get Contents of URL” by. Tik op “URL” en plak:') }} <code>{{ $endpoint }}</code></li>
        <li>{{ __('Tik op die pyltjie by die aksie. Kies “Method”: POST.') }}</li>
        <li>{{ __('By “Headers”, tik “Add new header”: sleutel') }} <code>Authorization</code>, {{ __('waarde: jou sleutel van stap 1 (dit begin met “Bearer”).') }}</li>
        <li>{{ __('By “Request Body” kies “Form”. Voeg ’n veld by met sleutel') }} <code>text</code> {{ __('en kies as waarde “Shortcut Input”.') }}</li>
        <li>{{ __('Voeg die aksie “Show Notification” by en kies “Contents of URL” as die teks. Tik “Done”.') }}</li>
    </ol>

    <h2>{{ __('3. Gebruik dit') }}</h2>
    <ol class="steps">
        <li>{{ __('In WhatsApp: druk lank op die boodskap, tik “Forward”, en dan die Deel-knoppie regs onder.') }}</li>
        <li>{{ __('Kies “Stuur na Tribe”. ’n Kennisgewing sê wat Tribe gelees het.') }}</li>
        <li>{{ __('Maak Tribe oop: die plan wag bo-aan jou tuisblad. Tik daarop, kyk dit na en stoor.') }}</li>
    </ol>
    <p class="muted small">{{ __('In ander apps: kies die teks, tik “Share…” en dan “Stuur na Tribe”.') }}</p>
</x-layout>
