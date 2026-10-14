<?php

namespace App\Mail;

use App\Models\Member;
use App\Services\EveningSummary;
use App\Support\Dates;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** @phpstan-import-type Summary from EveningSummary */
class EveningSummaryMail extends Mailable
{
    use Queueable;

    /** @param  Summary  $summary */
    public function __construct(public Member $member, public array $summary) {}

    public function envelope(): Envelope
    {
        $n = $this->summary['entries']->count();

        return new Envelope(subject: $n === 0
            ? __('Môre lyk rustig')
            : __('Môre: :n', ['n' => trans_choice(':count ding|:count dinge', $n)]).' · '.Dates::short($this->summary['date']));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.evening');
    }
}
