<?php

namespace App\Http\Controllers\Api\Technician;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteJobRequest;
use App\Http\Resources\TechnicianJobResource;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Services\JobCompletionService;
use App\Services\JobNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Serviserov dio. Majstor vidi samo naloge koji su njemu dodijeljeni.
 */
class JobController extends Controller
{
    public function __construct(
        private readonly JobNotifier $notifier,
        private readonly JobCompletionService $completion,
    ) {}

    /**
     * Moji nalozi. Podrazumijevano sortiranje ide po prozoru izlaska.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(JobStatus::values())],
            'date' => ['nullable', 'date'],
        ]);

        $jobs = $this->mojiNalozi($request)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['date'] ?? null, function ($query, $date) {
                $dan = Carbon::parse($date);

                $query->where(function ($where) use ($dan) {
                    $where->whereDate('scheduled_window_start', $dan->toDateString())
                        ->orWhere(function ($bez) use ($dan) {
                            $bez->whereNull('scheduled_window_start')
                                ->whereDate('deadline_at', $dan->toDateString());
                        });
                });
            })
            ->with(['category', 'user', 'property.city'])
            ->orderByRaw('scheduled_window_start is null')
            ->orderBy('scheduled_window_start')
            ->orderBy('deadline_at')
            ->get();

        return response()->json([
            'data' => TechnicianJobResource::collection($jobs),
        ]);
    }

    /**
     * Detalj naloga. Tudji nalog ne postoji, pa je odgovor 404.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $job = $this->nadji($request, $id);

        if (! $job) {
            return response()->json(['message' => 'Nalog nije pronađen.'], 404);
        }

        $job->loadMissing(['category', 'user', 'property.city', 'subscription.package', 'subscription.properties', 'photos']);

        $property = $job->property;
        $subscription = $job->subscription;

        $osnova = (new TechnicianJobResource($job))->toArray($request);

        return response()->json([
            'data' => array_merge($osnova, [
                'preferred_window' => $job->preferred_window,
                'findings' => $job->findings,
                'contact' => [
                    'name' => $property?->contact_name,
                    'note' => $property?->contact_note,
                ],
                'package' => $subscription?->package?->name,
                'entitlements' => [
                    'remaining_visits' => (int) ($property?->remaining_visits ?? 0),
                    'remaining_inspections' => (int) ($property?->remaining_inspections ?? 0),
                    'free_interventions' => (int) ($subscription?->free_interventions ?? 0),
                    'ide_na_naplatu' => $this->ideNaNaplatu($job),
                ],
                'photos' => $job->photos->map(fn (JobPhoto $photo) => [
                    'type' => $photo->type->value,
                    'url' => $photo->url(),
                ])->values(),
            ]),
        ]);
    }

    /**
     * Krenuo sam. Ponovljen poziv u stanju u toku nista ne mijenja.
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $job = $this->nadji($request, $id);

        if (! $job) {
            return response()->json(['message' => 'Nalog nije pronađen.'], 404);
        }

        if ($job->status === JobStatus::UToku) {
            return response()->json([
                'data' => new TechnicianJobResource($job),
                'message' => 'Izlazak je već pokrenut.',
            ]);
        }

        if ($job->status !== JobStatus::Zakazano) {
            return response()->json([
                'message' => 'Izlazak se pokreće samo na zakazanom nalogu.',
            ], 422);
        }

        $job->update(['status' => JobStatus::UToku]);

        if (! $this->notifier->alreadySent($job, 'majstor_krenuo')) {
            $this->notifier->send(JobStatus::UToku, $job->fresh(['user', 'technician']));
        }

        return response()->json([
            'data' => new TechnicianJobResource($job->refresh()),
            'message' => 'Klijent je obaviješten da ste krenuli.',
        ]);
    }

    /**
     * Zatvaranje naloga. Ide kroz isti servis kao i dispecerovo zatvaranje.
     */
    public function complete(CompleteJobRequest $request, int $id): JsonResponse
    {
        $job = $this->nadji($request, $id);

        if (! $job) {
            return response()->json(['message' => 'Nalog nije pronađen.'], 404);
        }

        $zavrsen = $this->completion->complete(
            job: $job,
            findings: (string) $request->input('findings'),
            items: $request->stavke(),
            materials: $request->materijali(),
            photosBefore: $request->fotografijePrije(),
            photosAfter: $request->fotografijePoslije(),
            actor: $request->user(),
        );

        $zavrsen->loadMissing('invoice');

        return response()->json([
            'data' => [
                'id' => $zavrsen->id,
                'number' => $zavrsen->number,
                'status' => $zavrsen->status->value,
                'completed_at' => $zavrsen->completed_at?->toIso8601String(),
                'warranty_until' => $zavrsen->warranty_until?->toDateString(),
                'visit_source' => $zavrsen->visit_source?->value,
                'invoice' => $zavrsen->invoice ? [
                    'id' => $zavrsen->invoice->id,
                    'number' => $zavrsen->invoice->number,
                    'status' => $zavrsen->invoice->status->value,
                    'labor_total' => (float) $zavrsen->invoice->labor_total,
                    'material_total' => (float) $zavrsen->invoice->material_total,
                    'total' => (float) $zavrsen->invoice->total,
                ] : null,
            ],
            'message' => 'Nalog je zatvoren. Izvještaj je poslan klijentu.',
        ]);
    }

    /**
     * Da li ovaj izlazak ide na naplatu rada. Majstor to mora znati unaprijed.
     */
    private function ideNaNaplatu(Job $job): bool
    {
        if ($job->type !== JobType::Redovno) {
            return false;
        }

        $subscription = $job->subscription;

        if ($subscription && $subscription->free_interventions > 0) {
            return false;
        }

        return (int) ($job->property?->remaining_visits ?? 0) === 0;
    }

    private function nadji(Request $request, int $id): ?Job
    {
        return Job::query()
            ->where('technician_id', $request->user()->technician?->id)
            ->whereKey($id)
            ->first();
    }

    private function mojiNalozi(Request $request)
    {
        return Job::query()->where('technician_id', $request->user()->technician?->id);
    }
}
