<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Surcharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Doplate su podatak u bazi. Admin mijenja iznos i da li se doplata primjenjuje.
 */
class SurchargeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $surcharges = Surcharge::query()->orderBy('sort')->get();

        return response()->json([
            'data' => $surcharges->map(fn (Surcharge $surcharge) => $this->red($surcharge))->values(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $surcharge = Surcharge::query()->find($id);

        if (! $surcharge) {
            return response()->json(['message' => 'Doplata nije pronađena.'], 404);
        }

        return response()->json(['data' => $this->red($surcharge)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $surcharge = Surcharge::query()->find($id);

        if (! $surcharge) {
            return response()->json(['message' => 'Doplata nije pronađena.'], 404);
        }

        $validated = $request->validate([
            'value' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'active' => ['nullable', 'boolean'],
        ], [
            'value.numeric' => 'Iznos doplate mora biti broj.',
            'value.min' => 'Iznos doplate ne može biti negativan.',
        ]);

        $izmjene = [];

        if (isset($validated['value'])) {
            $izmjene['value'] = (float) $validated['value'];
        }

        if ($request->has('active') && $validated['active'] !== null) {
            $izmjene['active'] = (bool) $validated['active'];
        }

        if ($izmjene !== []) {
            $surcharge->update($izmjene);
        }

        return response()->json([
            'data' => $this->red($surcharge->refresh()),
            'message' => 'Doplata je sačuvana.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function red(Surcharge $surcharge): array
    {
        return [
            'id' => $surcharge->id,
            'key' => $surcharge->key,
            'label' => $surcharge->label,
            'type' => $surcharge->type->value,
            'value' => (float) $surcharge->value,
            'active' => (bool) $surcharge->active,
            'sort' => (int) $surcharge->sort,
        ];
    }
}
