<x-layout :title="__('Kleinkinders')">
    <h1>{{ __('Kleinkinders') }}</h1>
    <p class="muted">{{ __('Groottes, gunstelinge, allergieë en wenslyste, vir as jy ’n present soek.') }}</p>

    <ul class="cards">
        @forelse ($children as $child)
            <li>
                <a class="card" href="{{ route('grandchildren.show', $child) }}" style="--c: {{ $child->displayColour() }}">
                    <span>
                        <strong>{{ $child->name }}</strong><br>
                        <span class="muted small">
                            {{ $child->household->name }}@if ($child->birthday) · {{ trans_choice(':count jaar oud|:count jaar oud', $child->ageOn($today)) }}@endif
                        </span>
                    </span>
                    @include('partials.icon', ['name' => 'next'])
                </a>
            </li>
        @empty
            <li class="empty">{{ __('Nog geen kinders bygevoeg nie. ’n Beheerder kan hulle by Meer › Familie byvoeg.') }}</li>
        @endforelse
    </ul>
</x-layout>
