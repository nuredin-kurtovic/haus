<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Genericki mejl za sva obavjestenja iz NotificationService.
 * Tekst je vec renderovan iz predloska u postavkama.
 */
class ObavjestenjeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $body,
        public string $templateKey = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'HAUS obavjestenje');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.obavjestenje',
            with: [
                'body' => $this->body,
                'templateKey' => $this->templateKey,
            ],
        );
    }
}
