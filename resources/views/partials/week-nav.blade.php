{{-- $weekStart (the Sunday), $previous and $next (URLs): "<  12 Okt tot 18 Okt  >" --}}
<div class="month-nav">
    <a class="button quiet round" href="{{ $previous }}" aria-label="{{ __('Vorige week') }}">@include('partials.icon', ['name' => 'back'])</a>
    <h1 class="week-title">{{ \App\Support\Dates::weekRange($weekStart) }}</h1>
    <a class="button quiet round" href="{{ $next }}" aria-label="{{ __('Volgende week') }}">@include('partials.icon', ['name' => 'next'])</a>
</div>
