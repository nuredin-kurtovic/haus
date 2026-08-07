<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\Technician;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Dispecerske izmjene naloga: dodjela majstora, termin i tranzicije stanja.
 *
 * Zatvaranje naloga namjerno ne ide ovuda. Nalog se zatvara nalazom, kroz
 * JobCompletionService, jer tek tamo nastaju stavke, faktura i garancija.
 */
class JobTransitionService
{
    public function __construct(
        private readonly ScheduleService $schedule,
        private readonly JobNotifier $notifier,
    ) {}

    /**
     * Primijeni izmjene i posalji obavjestenja koja ih prate.
     *
     * @return array<int, string> predlosci koji su poslani
     */
    public function update(
        Job $job,
        ?Technician $technician,
        ?Carbon $start,
        ?Carbon $end,
        ?JobStatus $status,
    ): array {
        if ($job->status === JobStatus::Zavrseno) {
            throw ValidationException::withMessages([
                'status' => 'Završen nalog se više ne mijenja.',
            ]);
        }

        $noviProzor = $this->provjeriProzor($job, $start, $end);
        $noviMajstor = $technician !== null && $technician->id !== $job->technician_id;

        $ciljno = $status ?? $job->status;

        $this->provjeriTranziciju($job, $ciljno, $technician, $start ?? $job->scheduled_window_start, $end ?? $job->scheduled_window_end);

        $izmjene = [];

        if ($technician !== null) {
            $izmjene['technician_id'] = $technician->id;
        }

        if ($noviProzor) {
            $izmjene['scheduled_window_start'] = $start;
            $izmjene['scheduled_window_end'] = $end;
        }

        if ($ciljno !== $job->status) {
            $izmjene['status'] = $ciljno;
        }

        $preStanje = $job->status;

        if ($izmjene !== []) {
            $job->update($izmjene);
        }

        return $this->javi($job->refresh(), $preStanje, $ciljno, $noviProzor, $noviMajstor);
    }

    /**
     * Prozor stize u paru i traje tacno 2 sata, unutar radnog vremena.
     */
    private function provjeriProzor(Job $job, ?Carbon $start, ?Carbon $end): bool
    {
        if ($start === null && $end === null) {
            return false;
        }

        if ($start === null || $end === null) {
            throw ValidationException::withMessages([
                'scheduled_window_start' => 'Termin se upisuje sa početkom i krajem prozora.',
            ]);
        }

        $greska = $this->schedule->greskaProzora($start, $end, (bool) $job->is_emergency);

        if ($greska !== null) {
            throw ValidationException::withMessages(['scheduled_window_start' => $greska]);
        }

        return $job->scheduled_window_start === null
            || $job->scheduled_window_end === null
            || ! $start->equalTo($job->scheduled_window_start)
            || ! $end->equalTo($job->scheduled_window_end);
    }

    private function provjeriTranziciju(Job $job, JobStatus $ciljno, ?Technician $technician, ?Carbon $start, ?Carbon $end): void
    {
        if ($ciljno === $job->status) {
            return;
        }

        match ($ciljno) {
            JobStatus::Novo => throw ValidationException::withMessages([
                'status' => 'Nalog se ne vraća u stanje novo.',
            ]),
            JobStatus::Zavrseno => throw ValidationException::withMessages([
                'status' => 'Nalog se zatvara nalazom. Koristite završetak naloga.',
            ]),
            JobStatus::Zakazano => $this->provjeriZakazivanje($job, $technician, $start, $end),
            JobStatus::UToku => $this->provjeriPokretanje($job),
        };
    }

    private function provjeriZakazivanje(Job $job, ?Technician $technician, ?Carbon $start, ?Carbon $end): void
    {
        if ($job->status !== JobStatus::Novo) {
            throw ValidationException::withMessages([
                'status' => 'Termin se zakazuje na novom nalogu.',
            ]);
        }

        if (! $technician && ! $job->technician_id) {
            throw ValidationException::withMessages([
                'technician_id' => 'Odaberite majstora prije zakazivanja.',
            ]);
        }

        if (! $start || ! $end) {
            throw ValidationException::withMessages([
                'scheduled_window_start' => 'Upišite termin izlaska prije zakazivanja.',
            ]);
        }
    }

    private function provjeriPokretanje(Job $job): void
    {
        if ($job->status !== JobStatus::Zakazano) {
            throw ValidationException::withMessages([
                'status' => 'Izlazak se pokreće samo na zakazanom nalogu.',
            ]);
        }
    }

    /**
     * Svaka tranzicija ide klijentu. Novi termin na vec zakazanom nalogu ide
     * ponovo, jer je to za klijenta nova informacija.
     *
     * @return array<int, string>
     */
    private function javi(Job $job, JobStatus $preStanje, JobStatus $ciljno, bool $noviProzor, bool $noviMajstor): array
    {
        $poslano = [];

        if ($ciljno === JobStatus::Zakazano && ($preStanje !== JobStatus::Zakazano || $noviProzor || $noviMajstor)) {
            $this->notifier->send(JobStatus::Zakazano, $job);
            $poslano[] = 'termin_potvrdjen';
        }

        if ($ciljno === JobStatus::UToku && $preStanje !== JobStatus::UToku
            && ! $this->notifier->alreadySent($job, 'majstor_krenuo')) {
            $this->notifier->send(JobStatus::UToku, $job);
            $poslano[] = 'majstor_krenuo';
        }

        return $poslano;
    }
}
