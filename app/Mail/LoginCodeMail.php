<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginCodeMail extends Mailable
{
    use Queueable;

    public function __construct(public string $name, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Jou Tribe-kode: :code', ['code' => $this->code]));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.login-code');
    }
}
