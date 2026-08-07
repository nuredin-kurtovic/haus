<?php

namespace App\Console\Commands;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\Subscription;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Probijen rok izlaska. Vrti se svake minute.
 *
 * Obecanje iz specifikacije: ako ne stignemo u roku, sljedeca intervencija je
 * besplatna i klijent to saznaje od nas, ne pitajuci.
 *
 * Odluka za naloge bez pretplate (naplata po cjenovniku, bez godisnjeg paketa):
 * rok se oznaci kao probijen da dispecer i izvjestaji to vide, ali kredita
 * nema i obavjestenje se ne salje. Kredit zivi na pretplati, pa ga nekome ko
 * pretplatu nema nemamo gdje upisati, a predlozak bi obecao nesto sto ne
 * mozemo ispuniti. Takav slucaj dispecer rjesava rucno.
 */
class CheckDeadlines extends Command
{
    protected $signature = 'haus:check-deadlines';

    protected $description = 'Nadje naloge kojima je probijen rok, upise besplatnu intervenciju i javi klijentu.';

    public function handle(NotificationService $notifications): int
    {
        $sada = Carbon::now();

        $nalozi = Job::query()
            ->where('deadline_at', '<', $sada)
            ->where('status', '!=', JobStatus::Zavrseno)
            ->whereNull('deadline_missed_at')
            ->with(['user', 'subscription'])
            ->orderBy('id')
            ->get();

        $kreditirano = 0;
        $bezPretplate = 0;

        foreach ($nalozi as $job) {
            $kreditiran = $this->oznaci($job, $sada);

            if ($kreditiran === null) {
                // Neko drugi je u medjuvremenu vec obradio isti nalog.
                continue;
            }

            if (! $kreditiran) {
                $bezPretplate++;

                continue;
            }

            $kreditirano++;

            if ($job->user) {
                $notifications->send($job->user, 'rok_probijen', [], $job);
            }
        }

        $this->info('Probijenih rokova: '.$nalozi->count().'. Upisanih besplatnih intervencija: '.$kreditirano.'. Bez pretplate: '.$bezPretplate.'.');

        return self::SUCCESS;
    }

    /**
     * Oznaci nalog i upisi kredit, sve u jednoj transakciji sa zakljucanim redom.
     *
     * @return bool|null true kad je kredit upisan, false kad nalog nema pretplatu,
     *                   null kad je nalog vec bio obradjen
     */
    private function oznaci(Job $job, Carbon $sada): ?bool
    {
        return DB::transaction(function () use ($job, $sada): ?bool {
            /** @var Job|null $zakljucan */
            $zakljucan = Job::query()->whereKey($job->getKey())->lockForUpdate()->first();

            if (! $zakljucan || $zakljucan->deadline_missed_at !== null) {
                return null;
            }

            $zakljucan->forceFill(['deadline_missed_at' => $sada])->save();

            if (! $zakljucan->subscription_id) {
                return false;
            }

            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()
                ->whereKey($zakljucan->subscription_id)
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                return false;
            }

            $subscription->increment('free_interventions');

            return true;
        });
    }
}
