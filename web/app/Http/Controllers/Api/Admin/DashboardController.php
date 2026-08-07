<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JobStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminJobResource;
use App\Models\Job;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Pocetni ekran dispecera: sta gori danas.
 */
class DashboardController extends Controller
{
    /** Podsjetnik na obnovu ide ovoliko dana unaprijed. */
    private const OBNOVA_DANA = 60;

    /** Prosjek zavrsetka se racuna na ovoliko dana unazad. */
    private const PROSJEK_DANA = 30;

    public function show(Request $request): JsonResponse
    {
        $danas = Carbon::now();

        $rokoviDanas = Job::query()
            ->whereDate('deadline_at', $danas->toDateString())
            ->where('status', '!=', JobStatus::Zavrseno->value)
            ->with(['category', 'technician', 'user', 'property.city'])
            ->orderBy('deadline_at')
            ->get();

        $rasporedDanas = Job::query()
            ->whereDate('scheduled_window_start', $danas->toDateString())
            ->with(['category', 'technician', 'user', 'property.city'])
            ->orderBy('scheduled_window_start')
            ->get();

        $obnove = Subscription::query()
            ->where('status', SubscriptionStatus::Aktivna)
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$danas, $danas->copy()->addDays(self::OBNOVA_DANA)])
            ->with(['user', 'package'])
            ->orderBy('ends_at')
            ->get();

        return response()->json([
            'kpi' => [
                'novi_danas' => Job::query()->whereDate('created_at', $danas->toDateString())->count(),
                'aktivni_nalozi' => Job::query()->where('status', '!=', JobStatus::Zavrseno->value)->count(),
                'rokovi_danas' => $rokoviDanas->count(),
                'prosjek_zavrsetka_h' => $this->prosjekZavrsetka(),
                'aktivne_pretplate' => Subscription::query()->where('status', SubscriptionStatus::Aktivna)->count(),
            ],
            'deadlines_today' => AdminJobResource::collection($rokoviDanas),
            'schedule_today' => $this->poProzorima($rasporedDanas, $request),
            'renewals_soon' => $obnove->map(fn (Subscription $subscription) => [
                'id' => $subscription->id,
                'client' => [
                    'id' => $subscription->user?->id,
                    'name' => $subscription->user?->name,
                    'email' => $subscription->user?->email,
                ],
                'package' => $subscription->package?->name,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'auto_renew' => (bool) $subscription->auto_renew,
                'dana_do_isteka' => (int) $danas->diffInDays($subscription->ends_at, false),
            ])->values(),
        ]);
    }

    /**
     * Raspored dana grupisan po prozoru izlaska, redom kako dan tece.
     *
     * @param  Collection<int, Job>  $jobs
     * @return array<int, array<string, mixed>>
     */
    private function poProzorima($jobs, Request $request): array
    {
        return $jobs
            ->groupBy(fn (Job $job) => $job->scheduled_window_start?->format('H:i').' do '.$job->scheduled_window_end?->format('H:i'))
            ->map(fn ($grupa, $prozor) => [
                'window' => $prozor,
                'starts_at' => $grupa->first()->scheduled_window_start?->toIso8601String(),
                'ends_at' => $grupa->first()->scheduled_window_end?->toIso8601String(),
                'jobs' => $grupa->map(fn (Job $job) => (new AdminJobResource($job))->toArray($request))->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * Prosjecno vrijeme od prijave do zavrsetka, u satima, zadnjih 30 dana.
     */
    private function prosjekZavrsetka(): ?float
    {
        $sati = Job::query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', Carbon::now()->subDays(self::PROSJEK_DANA))
            ->get()
            ->map(fn (Job $job) => $job->created_at->diffInMinutes($job->completed_at, true) / 60);

        if ($sati->isEmpty()) {
            return null;
        }

        return round($sati->avg(), 1);
    }
}
