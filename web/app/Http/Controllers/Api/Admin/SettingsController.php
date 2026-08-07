<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Sve postavke koje admin smije mijenjati, plus verzija cjenovnika za uvid.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->postavke()]);
    }

    /**
     * Djelimicna izmjena: upisuje se samo ono sto je stiglo.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        foreach ($request->izmjene() as $kljuc => $vrijednost) {
            $this->settings->set($kljuc, $vrijednost);
        }

        return response()->json([
            'data' => $this->postavke(),
            'message' => 'Postavke su sačuvane.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function postavke(): array
    {
        $data = $this->settings->many(UpdateSettingsRequest::KLJUCEVI);

        $data['price_list_version'] = (int) $this->settings->get('price_list_version', 1);
        $data['template_keys'] = UpdateSettingsRequest::TEMPLATE_KLJUCEVI;

        return $data;
    }
}
