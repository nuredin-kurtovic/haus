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
 * Uplatnica za pretplatu. PDF prilog dolazi kasnije, sada je dovoljan HTML
 * sa iznosom, pozivom na broj i uputom.
 */
class UplatnicaMail extends Mailable implements ShouldQueue
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
            subject: 'HAUS uplatnica za pretplatu, poziv na broj '.$this->invoice->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.uplatnica',
            with: [
                'invoice' => $this->invoice,
                'iznos' => (float) $this->invoice->total,
                'pozivNaBroj' => $this->invoice->number,
                'racun' => (string) config('services.haus.bank_account'),
                'primalac' => (string) config('services.haus.company_name'),
            ],
        );
    }
}
