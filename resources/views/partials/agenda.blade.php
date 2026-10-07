{{-- $days: occurrences grouped by Y-m-d; $today --}}
<ol class="agenda">
    @foreach ($days as $date => $occurrences)
        @php($day = \Carbon\CarbonImmutable::parse($date))
        <li id="d{{ $date }}" @class(['day', 'is-today' => $day->isSameDay($today)])>
            <div class="date" aria-hidden="true">
                <b>{{ $day->day }}</b>
                <span>{{ __(['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Sa', 'So'][$day->dayOfWeekIso - 1]) }}</span>
            </div>
            <div>
                <h3 class="visually-hidden">{{ $day->translatedFormat('l j F') }}</h3>
                <ul class="entries">
                    @foreach ($occurrences as $o)
                        <li>
                            @php($href = $o->event ? route('events.show', $o->event) : ($o->member?->isChild() ? route('grandchildren.show', $o->member) : null))
                            <{{ $href ? 'a' : 'div' }} class="entry" style="--c: {{ $o->colour }}" @if ($href) href="{{ $href }}" @endif>
                                <strong>
                                    @if ($o->isBirthday())@include('partials.icon', ['name' => 'cake'])@endif
                                    {{ $o->title }}
                                </strong>
                                @php($meta = array_filter([
                                    $o->time,
                                    $o->detail,
                                    $o->event?->location,
                                    $o->event?->isGathering() ? __('Byeenkoms by :house', ['house' => $o->event->hostHousehold?->name ?? '']) : null,
                                ]))
                                @if ($meta)
                                    <span class="meta">{{ implode(' · ', $meta) }}</span>
                                @endif
                            </{{ $href ? 'a' : 'div' }}>
                        </li>
                    @endforeach
                </ul>
            </div>
        </li>
    @endforeach
</ol>
