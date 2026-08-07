<?php

namespace App\Mail;

use App\Enums\JobPhotoType;
use App\Models\Job;
use App\Models\JobPhoto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Izvjestaj klijentu nakon zatvaranja naloga: nalaz, stavke, slike i garancija.
 * Salje se u redu poslova, odmah po zavrsetku.
 */
class IzvjestajMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Mejl je vazan, pa red pokusava tri puta. */
    public int $tries = 3;

    /** Razmak izmedju pokusaja: minuta, pa pet, pa petnaest. */
    public array $backoff = [60, 300, 900];

    public function __construct(public Job $job) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'HAUS izvještaj o intervenciji, nalog '.$this->job->number,
        );
    }

    public function content(): Content
    {
        $this->job->loadMissing(['items', 'materials', 'photos', 'invoice', 'category', 'technician']);

        return new Content(
            view: 'mail.izvjestaj',
            with: [
                'job' => $this->job,
                'stavke' => $this->job->items,
                'materijali' => $this->job->materials,
                'prije' => $this->slike(JobPhotoType::Prije),
                'poslije' => $this->slike(JobPhotoType::Poslije),
                'racun' => $this->job->invoice,
                'garancijaDo' => $this->job->warranty_until?->format('d.m.Y.'),
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    private function slike(JobPhotoType $type): array
    {
        return $this->job->photos
            ->filter(fn (JobPhoto $photo) => $photo->type === $type)
            ->map(fn (JobPhoto $photo) => $photo->url())
            ->values()
            ->all();
    }
}
