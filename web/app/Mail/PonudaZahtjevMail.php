<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * HAUS Pro sa 10 i vise stanova nema automatsku naplatu. Registracija postaje
 * zahtjev za ponudu i dispecer dobija ovaj mejl.
 */
class PonudaZahtjevMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'HAUS zahtjev za ponudu, HAUS Pro',
        );
    }

    public function content(): Content
    {
        $this->subscription->loadMissing(['user', 'package', 'properties.city']);

        return new Content(
            view: 'mail.ponuda-zahtjev',
            with: [
                'subscription' => $this->subscription,
                'klijent' => $this->subscription->user,
                'paket' => $this->subscription->package,
                'stanovi' => $this->subscription->properties,
            ],
        );
    }
}
