<?php

namespace App\Console\Commands;

use App\Mail\EveningSummaryMail;
use App\Models\Member;
use App\Models\PushSubscription;
use App\Services\EveningSummary;
use App\Services\Push;
use App\Services\Weather;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Every evening: tomorrow's entries, the weather, your lifts, and children's lifts nobody is down for yet. */
class RemindCommand extends Command
{
    protected $signature = 'tribe:remind';

    protected $description = 'Send tomorrow’s summary: push to devices with notifications on, email to those who asked';

    public function handle(EveningSummary $summaries, Push $push): int
    {
        $tomorrow = CarbonImmutable::tomorrow();
        $withPush = Push::enabled() ? PushSubscription::query()->select('member_id') : collect();
        $members = Member::query()->with('household')->whereNotNull('email')
            ->where(fn ($q) => $q->where('evening_email', true)->orWhereIn('id', $withPush))
            ->get();

        foreach ($members as $member) {
            $summary = $summaries->for($member, $tomorrow);

            if ($member->evening_email) {
                Mail::to($member->email)->send(new EveningSummaryMail($member, $summary));
            }

            if (! Push::enabled() || ! PushSubscription::query()->where('member_id', $member->id)->exists()) {
                continue;
            }

            $lines = $summary['entries']->map(fn ($o) => EveningSummary::entryLine($o));
            if ($lines->isNotEmpty() || $summary['mine'] !== []) {
                $body = $lines->take(5)->implode("\n").($lines->count() > 5 ? "\n…" : '');
                if ($summary['mine'] !== []) {
                    $body = trim($body."\n".implode("\n", $summary['mine']));
                }
                if ($summary['weather'] !== null) {
                    $body = Weather::line($summary['weather']).($summary['weather']['flags'] ? ' · '.implode(' · ', $summary['weather']['flags']) : '')."\n".$body;
                }
                $push->send($member, __('Môre, :day', ['day' => Dates::shortDay($tomorrow).' '.$tomorrow->translatedFormat('j F')]), $body, route('calendar'));
            }

            if ($summary['open'] !== []) {
                $push->send($member, __('Niemand ry nog nie'), implode("\n", $summary['open']), route('home'));
            }
        }

        $this->line('Reminded '.$members->count().' member(s).');

        return self::SUCCESS;
    }
}
