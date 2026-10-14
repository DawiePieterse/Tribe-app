{{-- $o: an Occurrence of a child's activity. --}}
@if ($o->needsLift)
    <span @class(['lift-line', 'open' => $o->liftOpen()])>{{ $o->liftLine() }}</span>
@endif
