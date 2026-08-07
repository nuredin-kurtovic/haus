<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Racun nakon uspjesne naplate.
 */
class RacunMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Mejl je vazan, pa red pokusava tri puta. */
    public int $tries = 3;

    /** Razmak izmedju pokusaja: minuta, pa pet, pa petnaest. */
    public array $backoff = [60, 300, 900];

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'HAUS racun broj '.$this->invoice->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.racun',
            with: [
                'invoice' => $this->invoice,
                'iznos' => (float) $this->invoice->total,
                'primalac' => (string) config('services.haus.company_name'),
            ],
        );
    }
}
