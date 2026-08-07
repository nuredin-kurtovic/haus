<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionSummaryResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Korisnik, uloga i sazetak aktivne pretplate. Bez aktivne pretplate je null.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = $user->activeSubscription()->with(['package', 'properties'])->first();

        return response()->json([
            'user' => new UserResource($user),
            'role' => $user->getRoleNames()->first(),
            'subscription' => $subscription ? new SubscriptionSummaryResource($subscription) : null,
        ]);
    }
}
