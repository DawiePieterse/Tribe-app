<?php

namespace App\Console\Commands;

use App\Mail\EveningSummaryMail;
use App\Models\Member;
use App\Models\PushSubscription;
use App\Services\EveningSummary;
use App\Services\Push;
use App\Services\Weather;
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
        $withPush = $push->enabled() ? PushSubscription::query()->distinct()->pluck('member_id') : collect();
        $members = Member::query()->with('household')->whereNotNull('email')
            ->where(fn ($q) => $q->where('evening_email', true)->orWhereIn('id', $withPush))
            ->get();

        foreach ($members as $member) {
            $summary = $summaries->for($member, $tomorrow);

            if ($member->evening_email) {
                Mail::to($member->email)->send(new EveningSummaryMail($member, $summary));
            }

            if (! $withPush->contains($member->id)) {
                continue;
            }

            $lines = $summary['entries']->map(fn ($o) => EveningSummary::entryLine($o));
            if ($lines->isNotEmpty() || $summary['mine'] !== []) {
                $body = collect([
                    $summary['weather'] !== null ? Weather::summary($summary['weather']) : null,
                    ...$lines->take(5),
                    $lines->count() > 5 ? '…' : null,
                    ...$summary['mine'],
                ])->filter()->implode("\n");
                $push->send($member, EveningSummary::heading($tomorrow), $body, route('calendar'));
            }

            if ($summary['open'] !== []) {
                $push->send($member, __('Niemand ry nog nie'), implode("\n", $summary['open']), route('home'));
            }
        }

        $push->flush();
        $this->line('Reminded '.$members->count().' member(s).');

        return self::SUCCESS;
    }
}
