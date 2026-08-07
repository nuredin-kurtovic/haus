<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateJobRequest;
use App\Http\Requests\CompleteJobRequest;
use App\Http\Resources\Admin\AdminJobResource;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\JobMaterial;
use App\Models\JobPhoto;
use App\Models\NotificationLog;
use App\Models\PriceCategory;
use App\Models\Technician;
use App\Services\DeadlineService;
use App\Services\JobCompletionService;
use App\Services\JobNotifier;
use App\Services\JobTransitionService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JobController extends Controller
{
    /** Koliko naloga stane na jednu stranicu liste. */
    private const PO_STRANICI = 20;

    public function __construct(
        private readonly JobTransitionService $transitions,
        private readonly JobCompletionService $completion,
        private readonly JobNotifier $notifier,
        private readonly NotificationService $notifications,
        private readonly DeadlineService $deadlines,
    ) {}

    /**
     * Lista naloga sa brojacima po stanju. Brojaci prate pretragu, ne filter
     * stanja, da dispecer vidi koliko ih je u kojem stanju.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(JobStatus::values())],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));

        $osnova = fn () => Job::query()->when($q !== '', fn ($query) => $query->where(function ($where) use ($q) {
            $where->where('number', 'like', '%'.$q.'%')
                ->orWhere('description', 'like', '%'.$q.'%')
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%'))
                ->orWhereHas('property', fn ($property) => $property->where('street', 'like', '%'.$q.'%'));
        }));

        $stranica = $osnova()
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->with(['category', 'technician', 'user', 'property.city'])
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? self::PO_STRANICI));

        $counts = $osnova()
            ->groupBy('status')
            ->selectRaw('status, count(*) as ukupno')
            ->pluck('ukupno', 'status');

        return response()->json([
            'data' => AdminJobResource::collection($stranica->getCollection()),
            'meta' => [
                'counts' => $this->brojaci($counts->all()),
                'total' => $stranica->total(),
                'per_page' => $stranica->perPage(),
                'current_page' => $stranica->currentPage(),
                'last_page' => $stranica->lastPage(),
            ],
        ]);
    }

    /**
     * Detalj naloga: klijent, pretplata, stavke, racun i historija obavjestenja.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $job = Job::query()
            ->with([
                'category', 'technician', 'user', 'property.city',
                'subscription.package', 'subscription.properties',
                'items', 'materials', 'photos', 'invoice', 'parent',
            ])
            ->find($id);

        if (! $job) {
            return response()->json(['message' => 'Nalog nije pronađen.'], 404);
        }

        $subscription = $job->subscription;

        return response()->json([
            'data' => array_merge((new AdminJobResource($job))->toArray($request), [
                'description' => $job->description,
                'preferred_window' => $job->preferred_window,
                'findings' => $job->findings,
                'warranty_until' => $job->warranty_until?->toDateString(),
                'visit_source' => $job->visit_source?->value,
                'parent_job' => $job->parent ? [
                    'id' => $job->parent->id,
                    'number' => $job->parent->number,
                ] : null,
                'contact' => [
                    'name' => $job->property?->contact_name,
                    'note' => $job->property?->contact_note,
                ],
                'subscription' => $subscription ? [
                    'id' => $subscription->id,
                    'status' => $subscription->status->value,
                    'package' => $subscription->package?->name,
                    'ends_at' => $subscription->ends_at?->toIso8601String(),
                    'free_interventions' => (int) $subscription->free_interventions,
                    'remaining_visits' => (int) $subscription->properties->sum('remaining_visits'),
                    'remaining_inspections' => (int) $subscription->properties->sum('remaining_inspections'),
                ] : null,
                'items' => $job->items->map(fn (JobItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'qty' => (int) $item->qty,
                    'base_price' => (float) $item->base_price,
                    'discount_pct' => (int) $item->discount_pct,
                    'line_total' => (float) $item->line_total,
                ])->values(),
                'materials' => $job->materials->map(fn (JobMaterial $material) => [
                    'id' => $material->id,
                    'name' => $material->name,
                    'purchase_price' => (float) $material->purchase_price,
                    'qty' => (float) $material->qty,
                    'markup_pct' => (int) $material->markup_pct,
                    'discount_pct' => (int) $material->discount_pct,
                    'line_total' => (float) $material->line_total,
                ])->values(),
                'photos' => $job->photos->map(fn (JobPhoto $photo) => [
                    'type' => $photo->type->value,
                    'url' => $photo->url(),
                ])->values(),
                'invoice' => $job->invoice ? [
                    'id' => $job->invoice->id,
                    'number' => $job->invoice->number,
                    'status' => $job->invoice->status->value,
                    'labor_total' => (float) $job->invoice->labor_total,
                    'material_total' => (float) $job->invoice->material_total,
                    'total' => (float) $job->invoice->total,
                    'paid_at' => $job->invoice->paid_at?->toIso8601String(),
                ] : null,
                'notifications' => NotificationLog::query()
                    ->where('job_id', $job->id)
                    ->orderBy('id')
                    ->get()
                    ->map(fn (NotificationLog $log) => [
                        'id' => $log->id,
                        'channel' => $log->channel->value,
                        'template_key' => $log->template_key,
                        'body' => $log->body,
                        'sent_at' => $log->sent_at?->toIso8601String(),
                    ])->values(),
            ]),
        ]);
    }

    /**
     * Dodjela majstora, termin i tranzicije stanja.
     */
    public function update(UpdateJobRequest $request, int $id): JsonResponse
    {
        $job = Job::query()->find($id);

        if (! $job) {
            return response()->json(['message' => 'Nalog nije pronađen.'], 404);
        }

        $poslano = $this->transitions->update(
            job: $job,
            technician: $request->majstor(),
            start: $request->pocetak(),
            end: $request->kraj(),
            status: $request->stanje(),
        );

        return response()->json([
            'data' => new AdminJobResource($job->refresh()),
            'notifications_sent' => $poslano,
            'message' => 'Nalog je ažuriran.',
        ]);
    }

    /**
     * Tacan tekst obavjestenja koje bi tranzicija poslala, bez slanja.
     */
    public function notificationPreview(Request $request, int $id): JsonResponse
    {
        $job = Job::query()->with(['technician', 'user'])->find($id);

        if (! $job) {
            return response()->json(['message' => 'Nalog nije pronađen.'], 404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(JobStatus::values())],
            'technician_id' => ['nullable', 'integer', Rule::exists('technicians', 'id')],
            'scheduled_window_start' => ['nullable', 'date'],
            'scheduled_window_end' => ['nullable', 'date'],
        ]);

        $status = JobStatus::from($validated['status']);
        $key = $this->notifier->templateKey($status);

        if (! $key) {
            return response()->json([
                'message' => 'Za to stanje nema obavještenja klijentu.',
            ], 422);
        }

        $technician = isset($validated['technician_id'])
            ? Technician::query()->find((int) $validated['technician_id'])
            : null;

        $body = $this->notifier->preview(
            $status,
            $job,
            isset($validated['scheduled_window_start']) ? Carbon::parse($validated['scheduled_window_start']) : null,
            isset($validated['scheduled_window_end']) ? Carbon::parse($validated['scheduled_window_end']) : null,
            $technician,
        );

        return response()->json([
            'data' => [
                'template_key' => $key,
                'body' => $body,
            ],
        ]);
    }

    /**
     * Dispecer zatvara nalog u ime majstora, kroz isti servis.
     */
    public function complete(CompleteJobRequest $request, int $id): JsonResponse
    {
        $job = Job::query()->find($id);

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

        return $this->show($request, $zavrsen->id);
    }

    /**
     * Garancijski nalog na vec zavrsen nalog. Ne naplacuje se i ne trosi izlazak.
     */
    public function warrantyJob(Request $request, int $id): JsonResponse
    {
        $original = Job::query()->with(['subscription.package', 'user'])->find($id);

        if (! $original) {
            return response()->json(['message' => 'Nalog nije pronađen.'], 404);
        }

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'min:10', 'max:2000'],
            'price_category_id' => ['nullable', 'integer', Rule::exists('price_categories', 'id')],
        ]);

        if ($original->status !== JobStatus::Zavrseno) {
            return response()->json([
                'message' => 'Garancijski nalog se otvara samo na završen nalog.',
            ], 422);
        }

        $package = $original->subscription?->package;

        $deadline = $package
            ? $this->deadlines->computeDeadline($package, false)
            : Carbon::now()->addHours(48);

        $category = isset($validated['price_category_id'])
            ? PriceCategory::query()->find((int) $validated['price_category_id'])
            : null;

        $job = DB::transaction(fn (): Job => Job::create([
            'number' => Job::nextNumber(),
            'user_id' => $original->user_id,
            'subscription_id' => $original->subscription_id,
            'subscription_property_id' => $original->subscription_property_id,
            'price_category_id' => $category?->id ?? $original->price_category_id,
            'type' => JobType::Garancija,
            'status' => JobStatus::Novo,
            'parent_job_id' => $original->id,
            'description' => $validated['description']
                ?? 'Garancijski izlazak po nalogu '.$original->number.'.',
            'is_emergency' => false,
            'deadline_at' => $deadline,
        ]));

        if ($original->user) {
            $this->notifications->send($original->user, 'prijava_primljena', [
                'broj' => $job->number,
                'rok' => $deadline->format('d.m.Y.').' do '.$deadline->format('H:i'),
            ], $job);
        }

        return response()->json([
            'data' => new AdminJobResource($job),
            'message' => 'Garancijski nalog je otvoren.',
        ], 201);
    }

    /**
     * Brojaci po stanju, uvijek sva stanja, i kad ih nema.
     *
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function brojaci(array $counts): array
    {
        $out = [];

        foreach (JobStatus::values() as $status) {
            $out[$status] = (int) ($counts[$status] ?? 0);
        }

        $out['ukupno'] = array_sum($out);

        return $out;
    }
}
