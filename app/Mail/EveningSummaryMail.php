<?php

namespace App\Mail;

use App\Models\Member;
use App\Support\Occurrence;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class EveningSummaryMail extends Mailable
{
    use Queueable;

    /** @param  array{date: CarbonImmutable, entries: Collection<int, Occurrence>, mine: list<string>, open: list<string>, weather: array{min: int, max: int, rain: int, wind: int, condition: string, flags: list<string>}|null}  $summary */
    public function __construct(public Member $member, public array $summary) {}

    public function envelope(): Envelope
    {
        $n = $this->summary['entries']->count();

        return new Envelope(subject: $n === 0
            ? __('Môre lyk rustig')
            : __('Môre: :n', ['n' => trans_choice(':count ding|:count dinge', $n)]).' · '.$this->summary['date']->translatedFormat('D j M'));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.evening');
    }
}
