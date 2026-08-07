<?php

namespace App\Services;

use App\Enums\JobPhotoType;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Models\PriceCategory;
use App\Models\Subscription;
use App\Models\SubscriptionProperty;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Prijava kvara. Jedno mjesto koje zna broj naloga, rok i prvo obavjestenje.
 *
 * Nalog se prima i kad su izlasci potroseni. Naplata se odlucuje pri zavrsetku,
 * po redoslijedu kredit > izlazak > cjenovnik sa popustom paketa.
 */
class JobService
{
    /** Sudar na jedinstvenom broju naloga je rijedak, par pokusaja je dovoljno. */
    private const MAX_POKUSAJA = 5;

    public function __construct(
        private readonly DeadlineService $deadlines,
        private readonly NotificationService $notifications,
    ) {}

    public function prijaviKvar(
        User $user,
        Subscription $subscription,
        SubscriptionProperty $property,
        PriceCategory $category,
        string $description,
        bool $emergency,
        ?string $preferredWindow = null,
        ?UploadedFile $photo = null,
    ): Job {
        $subscription->loadMissing('package');

        $deadline = $this->deadlines->computeDeadline($subscription->package, $emergency);

        $job = $this->saJedinstvenimBrojem(fn (string $number): Job => Job::create([
            'number' => $number,
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'subscription_property_id' => $property->id,
            'price_category_id' => $category->id,
            'type' => JobType::Redovno,
            'status' => JobStatus::Novo,
            'description' => $description,
            'is_emergency' => $emergency,
            'preferred_window' => $preferredWindow,
            // Rok se racuna jednom, pri prijavi, i nikad se ne mijenja.
            'deadline_at' => $deadline,
        ]));

        if ($photo) {
            $this->spremiFotografiju($job, $photo);
        }

        $this->notifications->send($user, 'prijava_primljena', [
            'broj' => $job->number,
            'rok' => $this->rok($job->deadline_at),
        ], $job);

        return $job;
    }

    /**
     * Klijentova fotografija pri prijavi je kontekst kvara, ide kao tip prije.
     */
    private function spremiFotografiju(Job $job, UploadedFile $photo): JobPhoto
    {
        $path = $photo->store('jobs/'.$job->id, JobPhoto::DISK);

        return JobPhoto::create([
            'job_id' => $job->id,
            'type' => JobPhotoType::Prije,
            'path' => $path,
        ]);
    }

    /**
     * Rok u tekstu obavjestenja. Bez tacke na kraju, predlozak je vec ima.
     */
    private function rok(Carbon $deadline): string
    {
        return $deadline->format('d.m.Y.').' do '.$deadline->format('H:i');
    }

    /**
     * Broj naloga se cita i upisuje u istoj transakciji. Ako dva zahtjeva
     * ipak uhvate isti broj, jedinstveni indeks puca i pokusavamo ponovo.
     *
     * @param  callable(string): Job  $factory
     */
    private function saJedinstvenimBrojem(callable $factory): Job
    {
        for ($pokusaj = 1; $pokusaj <= self::MAX_POKUSAJA; $pokusaj++) {
            try {
                return DB::transaction(fn (): Job => $factory(Job::nextNumber()));
            } catch (QueryException $e) {
                if (! $this->jeSudarBroja($e) || $pokusaj === self::MAX_POKUSAJA) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Broj naloga nije generisan.');
    }

    private function jeSudarBroja(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
