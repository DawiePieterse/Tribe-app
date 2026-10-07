<x-layout :title="__('Familie')">
    <a class="back" href="{{ route('more') }}">@include('partials.icon', ['name' => 'back']) {{ __('Meer') }}</a>
    <h1>{{ __('Familie') }}</h1>
    <p class="muted">{{ __('Huise, mense en uitnodigings. Net beheerders sien hierdie bladsy.') }}</p>

    @foreach ($households as $household)
        <section style="--c: {{ $household->colour }}">
            <h2><span class="dot"></span> {{ $household->name }}</h2>
            <ul class="items">
                @foreach ($household->members as $member)
                    <li class="item">
                        <span class="title">
                            <span>{{ $member->name }}@if ($member->is_admin) <span class="muted small">({{ __('beheerder') }})</span>@endif</span>
                            <span class="meta">
                                {{ $member->isChild() ? __('Kind') : ($member->email ?? __('Geen e-posadres nie')) }}
                                @if ($member->canLogIn())
                                    · {{ $member->last_login_at ? __('laas aangemeld :when', ['when' => $member->last_login_at->diffForHumans()]) : __('nog nooit aangemeld nie') }}
                                @endif
                            </span>
                        </span>
                        @if ($member->canLogIn())
                            <form method="post" action="{{ route('family.members.invite', $member) }}">
                                @csrf
                                <button type="submit" class="quiet small">{{ __('Uitnodig') }}</button>
                            </form>
                        @endif
                        <a class="button quiet small" href="{{ route('family.members.edit', $member) }}">{{ __('Wysig') }}</a>
                    </li>
                @endforeach
            </ul>
            <p class="actions small" style="margin-top: 10px">
                <a href="{{ route('family.members.create', ['huis' => $household->id]) }}">{{ __('+ Grootmens') }}</a>
                <a href="{{ route('family.members.create', ['huis' => $household->id, 'soort' => 'child']) }}">{{ __('+ Kind') }}</a>
            </p>
            <details>
                <summary class="small">{{ __('Verander naam of kleur') }}</summary>
                <form method="post" action="{{ route('family.households.update', $household) }}" class="stack" style="margin-top: 10px">
                    @csrf @method('put')
                    <label>{{ __('Naam') }}<input type="text" name="name" maxlength="100" required value="{{ $household->name }}"></label>
                    @include('family.swatches', ['current' => $household->colour, 'id' => 'h'.$household->id])
                    <div><button type="submit" class="small">{{ __('Stoor') }}</button></div>
                </form>
            </details>
        </section>
    @endforeach

    <h2>{{ __('Nuwe huis') }}</h2>
    <form method="post" action="{{ route('family.households.store') }}" class="stack">
        @csrf
        <label>{{ __('Naam') }}<input type="text" name="name" maxlength="100" required placeholder="{{ __('Bv. Mia en Dewan') }}" value="{{ old('name') }}">
            @error('name')<span class="error">{{ $message }}</span>@enderror</label>
        @include('family.swatches', ['current' => $colours[$households->count() % count($colours)], 'id' => 'new'])
        <div><button type="submit">{{ __('Voeg huis by') }}</button></div>
    </form>
</x-layout>
