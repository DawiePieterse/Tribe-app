<x-layout :title="__('Meer')">
    <h1>{{ __('Meer') }}</h1>
    <p class="muted" style="margin-top: 6px">{{ $me->name }} · {{ $me->household->name }}</p>

    <ul class="menu">
        <li><a href="{{ route('share.setup') }}" style="--c: #34C759">@include('partials.icon', ['name' => 'share']) {{ __('Stuur na Tribe') }}</a></li>
        <li><a href="{{ route('feeds.index') }}" style="--c: #FF3B30">@include('partials.icon', ['name' => 'calendar']) {{ __('Kalenders') }}</a></li>
        <li><a href="{{ route('contacts.index') }}" style="--c: #FF9500">@include('partials.icon', ['name' => 'book']) {{ __('Belangrike nommers') }}</a></li>
        @if ($me->is_admin)
            <li><a href="{{ route('family.index') }}" style="--c: #5856D6">@include('partials.icon', ['name' => 'people']) {{ __('Familie en uitnodigings') }}</a></li>
        @endif
        <li><a href="{{ route('settings') }}" style="--c: #8E8E93">@include('partials.icon', ['name' => 'gear']) {{ __('My instellings') }}</a></li>
        <li>
            <form method="post" action="{{ route('logout') }}" data-logout>
                @csrf
                <button type="submit">@include('partials.icon', ['name' => 'door']) {{ __('Meld af') }}</button>
            </form>
        </li>
    </ul>
</x-layout>
