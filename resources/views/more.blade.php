<x-layout :title="__('Meer')">
    <h1>{{ __('Meer') }}</h1>
    <p class="muted">{{ $me->name }} · {{ $me->household->name }}</p>

    <ul class="menu">
        <li><a href="{{ route('contacts.index') }}">@include('partials.icon', ['name' => 'book']) {{ __('Belangrike nommers') }}</a></li>
        @if ($me->is_admin)
            <li><a href="{{ route('family.index') }}">@include('partials.icon', ['name' => 'people']) {{ __('Familie en uitnodigings') }}</a></li>
        @endif
        <li><a href="{{ route('settings') }}">@include('partials.icon', ['name' => 'gear']) {{ __('My instellings') }}</a></li>
        <li>
            <form method="post" action="{{ route('logout') }}" data-logout>
                @csrf
                <button type="submit">@include('partials.icon', ['name' => 'door']) {{ __('Meld af') }}</button>
            </form>
        </li>
    </ul>
</x-layout>
