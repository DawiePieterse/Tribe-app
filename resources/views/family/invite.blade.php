@php($message = __('Hallo :name, hier is jou skakel na Tribe, ons familie-app. Maak dit oop op jou iPhone of iPad: :link', ['name' => $member->firstName(), 'link' => $link]))
<x-layout :title="__('Uitnodiging')">
    <a class="back" href="{{ route('family.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Familie') }}</a>
    <h1>{{ __('Uitnodiging vir :name', ['name' => $member->name]) }}</h1>
    <p>{{ __('Stuur hierdie skakel net vir :name. Dit werk een keer, vir :days dae, en meld hulle sonder ’n wagwoord aan.', ['name' => $member->firstName(), 'days' => \App\Models\Invite::VALID_DAYS]) }}</p>

    <div class="copybox">
        <label class="visually-hidden" for="invite-link">{{ __('Skakel') }}</label>
        <input id="invite-link" type="text" value="{{ $link }}" readonly>
        <button type="button" class="quiet" data-copy="#invite-link" data-copied="{{ __('Gekopieer') }}">{{ __('Kopieer') }}</button>
    </div>

    <p style="margin-top: 16px">
        <a class="button" href="https://wa.me/?text={{ rawurlencode($message) }}">@include('partials.icon', ['name' => 'share']) {{ __('Stuur op WhatsApp') }}</a>
    </p>
    <p class="muted small">{{ __('As jy weer op Uitnodig tik, hou hierdie skakel op werk.') }}</p>
</x-layout>
