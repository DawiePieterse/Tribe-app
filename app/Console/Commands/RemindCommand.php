<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\PushSubscription;
use App\Services\Agenda;
use App\Services\Push;
use App\Support\Dates;
use App\Support\Occurrence;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Every evening: tomorrow's entries, and children's lifts nobody is down for yet. */
class RemindCommand extends Command
{
    protected $signature = 'tribe:remind';

    protected $description = 'Push tomorrow’s agenda and open lifts to everyone with notifications on';

    public function handle(Agenda $agenda, Push $push): int
    {
        if (! Push::enabled()) {
            $this->line('Push notifications are not set up (tribe:vapid-keys).');

            return self::SUCCESS;
        }

        $tomorrow = CarbonImmutable::tomorrow();
        $members = Member::query()->whereIn('id', PushSubscription::query()->select('member_id'))->get();

        foreach ($members as $member) {
            $coming = $agenda->between($member, $tomorrow, $tomorrow->addDays(2));
            $lines = $coming->filter(fn (Occurrence $o) => $o->date->isSameDay($tomorrow))
                ->map(fn (Occurrence $o) => trim(($o->time ? $o->time.' ' : '').$o->title.($o->event?->member ? ' ('.$o->event->member->firstName().')' : '')));

            if ($lines->isNotEmpty()) {
                $push->send($member, __('Môre, :day', ['day' => Dates::shortDay($tomorrow).' '.$tomorrow->translatedFormat('j F')]),
                    $lines->take(5)->implode("\n").($lines->count() > 5 ? "\n…" : ''), route('calendar'));
            }

            $open = $coming->filter(fn (Occurrence $o) => $o->liftOpen() && $o->event?->member?->household_id === $member->household_id);
            if ($open->isNotEmpty()) {
                $push->send($member, __('Niemand ry nog nie'),
                    $open->map(fn (Occurrence $o) => Dates::shortDay($o->date).' '.$o->title.' ('.$o->event?->member?->firstName().'): '.$o->lift)->implode("\n"),
                    route('home'));
            }
        }

        $this->line('Reminded '.$members->count().' member(s).');

        return self::SUCCESS;
    }
}
