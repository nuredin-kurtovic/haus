<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SurchargeResource;
use App\Models\Surcharge;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SurchargeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $surcharges = Surcharge::query()
            ->active()
            ->orderBy('sort')
            ->get();

        return SurchargeResource::collection($surcharges);
    }
}
