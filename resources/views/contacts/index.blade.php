@php($labels = ['emergency' => __('Noodnommers'), 'doctor' => __('Dokters'), 'school' => __('Skole'), 'babysitter' => __('Oppassers'), 'other' => __('Ander')])
<x-layout :title="__('Belangrike nommers')">
    <a class="back" href="{{ route('more') }}">@include('partials.icon', ['name' => 'back']) {{ __('Meer') }}</a>
    <div class="head">
        <h1>{{ __('Belangrike nommers') }}</h1>
        <a class="button small" href="{{ route('contacts.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('Nuwe') }}</a>
    </div>

    @forelse ($groups as $category => $contacts)
        <h2>{{ $labels[$category] }}</h2>
        <ul class="cards">
            @foreach ($contacts as $contact)
                <li class="card" style="--c: {{ $contact->isFamilyWide() ? 'var(--action)' : 'var(--ok)' }}">
                    <span>
                        <strong>{{ $contact->name }}</strong>
                        @if ($contact->notes)<br><span class="muted small">{{ $contact->notes }}</span>@endif
                        <br><a class="small" href="{{ route('contacts.edit', $contact) }}">{{ __('Wysig') }}</a>
                    </span>
                    @if ($contact->phone)
                        <a class="button quiet small" href="{{ $contact->telLink() }}">@include('partials.icon', ['name' => 'phone']) {{ $contact->phone }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @empty
        <p class="empty">{{ __('Nog geen nommers nie. Voeg die dokter, skool en noodnommers by sodat almal dit kan kry.') }}</p>
    @endforelse
</x-layout>
