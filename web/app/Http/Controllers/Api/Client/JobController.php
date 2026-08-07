<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreJobRequest;
use App\Http\Resources\JobDetailResource;
use App\Http\Resources\JobListResource;
use App\Models\Job;
use App\Services\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * Klijentovi nalozi, najnoviji prvi.
     */
    public function index(Request $request): JsonResponse
    {
        $jobs = Job::query()
            ->where('user_id', $request->user()->id)
            ->with(['category', 'technician'])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => JobListResource::collection($jobs),
        ]);
    }

    /**
     * Prijava kvara.
     *
     * Nalog se prima i kad su izlasci potroseni. Sta se naplacuje odlucuje se
     * pri zavrsetku, ne pri prijavi.
     */
    public function store(StoreJobRequest $request, JobService $jobs): JsonResponse
    {
        $job = $jobs->prijaviKvar(
            user: $request->user(),
            subscription: $request->pretplata(),
            property: $request->stan(),
            category: $request->kategorija(),
            description: (string) $request->input('description'),
            emergency: $request->jeHitno(),
            preferredWindow: $request->input('preferred_window'),
            photo: $request->file('photo'),
        );

        return response()->json([
            'job' => [
                'id' => $job->id,
                'number' => $job->number,
                'deadline_at' => $job->deadline_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Detalj naloga. Tudji nalog ne postoji, pa je odgovor 404.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $job = Job::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->first();

        if (! $job) {
            return response()->json(['message' => 'Nalog nije pronađen.'], 404);
        }

        return response()->json([
            'data' => new JobDetailResource($job),
        ]);
    }
}
