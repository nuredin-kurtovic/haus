<?php

namespace App\Http\Controllers\Api;

use App\Enums\CityStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CityController extends Controller
{
    /**
     * Svi gradovi. Registracija koristi samo status aktivan, ali mapa
     * prikazuje i gradove u pripremi.
     */
    public function index(): AnonymousResourceCollection
    {
        $cities = City::query()
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CityStatus::Aktivan->value])
            ->orderBy('name')
            ->get();

        return CityResource::collection($cities);
    }
}
