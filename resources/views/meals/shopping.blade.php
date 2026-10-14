<x-layout :title="__('Inkopies vir die week')">
    <a class="back" href="{{ route('meals.week', ['week' => $weekStart->toDateString()]) }}">@include('partials.icon', ['name' => 'back']) {{ __('Weekmenu') }}</a>
    <h1>{{ __('Inkopies vir die week') }}</h1>
    <p class="muted">{{ __(':from tot :to', ['from' => $weekStart->translatedFormat('j F'), 'to' => $weekStart->addDays(6)->translatedFormat('j F')]) }}</p>

    @php($lines = $recipes->flatMap(fn ($r) => $r->ingredientLines())->unique()->values()->all())
    @if ($lines === [])
        <p class="empty">{{ __('Daar is nog geen resepte op hierdie week se menu nie. Ete sonder ’n resep, soos “braai”, het nie bestanddele nie.') }}</p>
    @else
        <p class="small muted">{{ __('Uit:') }} {{ $recipes->pluck('title')->implode(', ') }}</p>
        @include('partials.to-list', ['lines' => $lines, 'lists' => $lists])
    @endif
</x-layout>
