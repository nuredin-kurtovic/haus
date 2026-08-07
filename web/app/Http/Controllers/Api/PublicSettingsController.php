<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;

class PublicSettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Samo postavke koje gost smije vidjeti.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'radno_vrijeme' => $this->settings->get('radno_vrijeme', []),
                'satnica_redovna' => (float) $this->settings->get('satnica_redovna', 0),
                'satnica_hitna' => (float) $this->settings->get('satnica_hitna', 0),
                'izlazak_bez_pretplate' => (float) $this->settings->get('izlazak_bez_pretplate', 0),
                'ukljuceno_minuta' => (int) $this->settings->get('ukljuceno_minuta', 0),
                'materijal_marza_pct' => (int) $this->settings->get('materijal_marza_pct', 0),
                'price_list_version' => (int) $this->settings->get('price_list_version', 1),
            ],
        ]);
    }
}
