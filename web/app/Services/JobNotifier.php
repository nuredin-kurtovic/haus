<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\NotificationLog;
use App\Models\Technician;
use Illuminate\Support\Carbon;

/**
 * Jedno mjesto koje zna koji predlozak ide uz koju tranziciju naloga i sa
 * kojim varijablama. Isti kod puni i stvarno slanje i admin pregled teksta,
 * pa dispecer u pregledu vidi tacno ono sto ce klijent dobiti.
 */
class JobNotifier
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ScheduleService $schedule,
    ) {}

    /**
     * Predlozak koji prati prelazak u dato stanje.
     */
    public function templateKey(JobStatus $status): ?string
    {
        return match ($status) {
            JobStatus::Zakazano => 'termin_potvrdjen',
            JobStatus::UToku => 'majstor_krenuo',
            JobStatus::Zavrseno => 'zavrseno',
            default => null,
        };
    }

    /**
     * Varijable predloska. Prosledjeni prozor i majstor imaju prednost nad
     * onim sto je na nalogu, jer pregled gleda tranziciju koja tek dolazi.
     *
     * @return array<string, string>
     */
    public function vars(
        JobStatus $status,
        Job $job,
        ?Carbon $start = null,
        ?Carbon $end = null,
        ?Technician $technician = null,
    ): array {
        $start ??= $job->scheduled_window_start;
        $end ??= $job->scheduled_window_end;
        $technician ??= $job->technician;

        return match ($status) {
            JobStatus::Zakazano => [
                'dan' => $start ? $this->schedule->danNaBosanskom($start) : '',
                'datum' => $start?->format('d.m.Y') ?? '',
                'od' => $start?->format('H:i') ?? '',
                'do' => $end?->format('H:i') ?? '',
                'majstor' => $technician?->name ?? '',
                'broj' => $job->number,
            ],
            JobStatus::UToku => [
                'majstor' => $technician?->name ?? '',
                'do' => $end?->format('H:i') ?? '',
                'broj' => $job->number,
            ],
            JobStatus::Zavrseno => [
                'broj' => $job->number,
                'garancija_datum' => $job->warranty_until?->format('d.m.Y') ?? '',
            ],
            default => [],
        };
    }

    /**
     * Posalji obavjestenje koje prati prelazak u dato stanje.
     */
    public function send(JobStatus $status, Job $job): void
    {
        $key = $this->templateKey($status);
        $user = $job->user;

        if (! $key || ! $user) {
            return;
        }

        $this->notifications->send($user, $key, $this->vars($status, $job), $job);
    }

    /**
     * Tekst bez slanja.
     */
    public function preview(
        JobStatus $status,
        Job $job,
        ?Carbon $start = null,
        ?Carbon $end = null,
        ?Technician $technician = null,
    ): ?string {
        $key = $this->templateKey($status);

        if (! $key) {
            return null;
        }

        return $this->notifications->preview($key, $this->vars($status, $job, $start, $end, $technician));
    }

    /**
     * Da li je predlozak za taj nalog vec poslan. Cuva klijenta od duplog
     * obavjestenja kad majstor javi polazak, pa dispecer prebaci stanje.
     */
    public function alreadySent(Job $job, string $templateKey): bool
    {
        return NotificationLog::query()
            ->where('job_id', $job->id)
            ->where('template_key', $templateKey)
            ->exists();
    }
}
