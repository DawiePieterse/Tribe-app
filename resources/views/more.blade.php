<x-layout :title="__('Meer')">
    <h1>{{ __('Meer') }}</h1>
    <p class="muted" style="margin-top: 6px">{{ $me->name }} · {{ $me->household->name }}</p>

    <ul class="menu">
        @unless ($me->isHelper())
            <li><a href="{{ route('share.setup') }}" class="tile-share">@include('partials.icon', ['name' => 'share']) {{ __('Stuur na Tribe') }}</a></li>
            <li><a href="{{ route('feeds.index') }}" class="tile-feeds">@include('partials.icon', ['name' => 'calendar']) {{ __('Kalenders') }}</a></li>
        @endunless
        <li><a href="{{ route('contacts.index') }}" class="tile-contacts">@include('partials.icon', ['name' => 'book']) {{ __('Belangrike nommers') }}</a></li>
        @if ($me->is_admin)
            <li><a href="{{ route('family.index') }}" class="tile-family">@include('partials.icon', ['name' => 'people']) {{ __('Familie en uitnodigings') }}</a></li>
        @endif
        <li><a href="{{ route('settings') }}" class="tile-settings">@include('partials.icon', ['name' => 'gear']) {{ __('My instellings') }}</a></li>
        <li>
            <form method="post" action="{{ route('logout') }}" data-logout>
                @csrf
                <button type="submit">@include('partials.icon', ['name' => 'door']) {{ __('Meld af') }}</button>
            </form>
        </li>
    </ul>
</x-layout>
