<?php

namespace App\Mail;

use App\Models\SubscriptionProperty;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Zahtjev klijenta za promjenu adrese. Adresu mijenja dispecer rucno, jer za
 * njom vise prava: grad mora biti aktivan, a prava izlazaka idu uz adresu.
 */
class PromjenaAdreseMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Mejl je vazan, pa red pokusava tri puta. */
    public int $tries = 3;

    /** Ako je model u medjuvremenu obrisan, mejl se tiho uklanja umjesto da puca. */
    public bool $deleteWhenMissingModels = true;

    /** Razmak izmedju pokusaja: minuta, pa pet, pa petnaest. */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public User $klijent,
        public ?SubscriptionProperty $stan,
        public string $poruka,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'HAUS zahtjev za promjenu adrese, '.$this->klijent->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.obavjestenje',
            with: [
                'body' => $this->tekst(),
                'templateKey' => 'promjena_adrese',
            ],
        );
    }

    private function tekst(): string
    {
        $this->stan?->loadMissing('city');

        $adresa = $this->stan
            ? $this->stan->street.', '.($this->stan->city?->name ?? '')
            : 'nije upisana';

        return 'Zahtjev za promjenu adrese. Klijent: '.$this->klijent->name
            .' ('.$this->klijent->email.'). Trenutna adresa: '.trim($adresa, ', ')
            .'. Poruka klijenta: '.$this->poruka;
    }
}
