<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $verificationCode) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('site.verification.email_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            with: ['verificationCode' => $this->verificationCode],
        );
    }
}
