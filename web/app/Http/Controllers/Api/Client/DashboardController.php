<?php

namespace App\Http\Controllers\Api\Client;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Koliko zadnjih naloga ide na pocetni ekran. */
    private const ZADNJIH_NALOGA = 5;

    /**
     * Pocetni ekran klijenta: stanje pretplate, nalog u toku i zadnji nalozi.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = $user->currentSubscription();
        $subscription?->loadMissing(['package', 'properties']);

        $active = Job::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', JobStatus::Zavrseno->value)
            ->with(['category', 'technician'])
            ->latest('id')
            ->first();

        $recent = Job::query()
            ->where('user_id', $user->id)
            ->with('category')
            ->latest('id')
            ->limit(self::ZADNJIH_NALOGA)
            ->get();

        return response()->json([
            'subscription' => $subscription ? $this->pretplata($subscription) : null,
            'active_job' => $active ? $this->nalogUToku($active) : null,
            'recent_jobs' => $this->zadnjiNalozi($recent),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pretplata(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'package' => [
                'name' => $subscription->package->name,
                'slug' => $subscription->package->slug,
            ],
            'status' => $subscription->status->value,
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            // Prava se vode po adresi, klijent na pocetnom ekranu vidi zbir.
            'remaining_visits' => (int) $subscription->properties->sum('remaining_visits'),
            'free_interventions' => (int) $subscription->free_interventions,
            'remaining_inspections' => (int) $subscription->properties->sum('remaining_inspections'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nalogUToku(Job $job): array
    {
        return [
            'id' => $job->id,
            'number' => $job->number,
            'status' => $job->status->value,
            'category' => $job->category?->name,
            'deadline_at' => $job->deadline_at?->toIso8601String(),
            'scheduled_window_start' => $job->scheduled_window_start?->toIso8601String(),
            'scheduled_window_end' => $job->scheduled_window_end?->toIso8601String(),
            'technician' => $job->technician ? ['name' => $job->technician->name] : null,
            'steps' => $job->steps(),
        ];
    }

    /**
     * @param  Collection<int, Job>  $jobs
     * @return array<int, array<string, mixed>>
     */
    private function zadnjiNalozi(Collection $jobs): array
    {
        return $jobs->map(fn (Job $job) => [
            'id' => $job->id,
            'number' => $job->number,
            'status' => $job->status->value,
            'type' => $job->type->value,
            'category' => $job->category?->name,
            'created_at' => $job->created_at?->toIso8601String(),
        ])->values()->all();
    }
}
