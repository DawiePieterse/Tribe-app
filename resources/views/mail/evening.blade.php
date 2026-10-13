{{ __('Goeienaand :name', ['name' => $member->firstName()]) }}

{{ __('Môre, :day:', ['day' => \App\Support\Dates::shortDay($summary['date']).' '.$summary['date']->translatedFormat('j F')]) }}
@if ($summary['weather'])
{{ __('Weer:') }} {{ \App\Services\Weather::line($summary['weather']) }}
@foreach ($summary['weather']['flags'] as $flag)
  {{ $flag }}
@endforeach
@endif

@if ($summary['entries']->isEmpty())
{{ __('Niks op die kalender nie. Geniet die dag!') }}
@else
@foreach ($summary['entries'] as $o)
- {{ \App\Services\EveningSummary::entryLine($o) }}
@endforeach
@endif
@if ($summary['mine'] !== [])

{{ __('Jou ritte:') }}
@foreach ($summary['mine'] as $line)
- {{ $line }}
@endforeach
@endif
@if ($summary['open'] !== [])

{{ __('Niemand ry nog nie:') }}
@foreach ($summary['open'] as $line)
- {{ $line }}
@endforeach
@endif

{{ route('calendar') }}
