<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Models\PriceCategory;
use App\Models\PriceItem;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Objavljene pozicije po kategorijama. Majstor bira stavke, cijenu za
     * klijenta racuna server pri zatvaranju naloga.
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $categories = PriceCategory::query()
            ->with(['items' => function ($items) use ($q) {
                $items->where('active', true)->orderBy('sort');

                if ($q !== '') {
                    $items->where('name', 'like', '%'.$q.'%');
                }
            }])
            ->orderBy('sort')
            ->get()
            ->filter(fn (PriceCategory $category) => $category->items->isNotEmpty())
            ->map(fn (PriceCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'items' => $category->items->map(fn (PriceItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'base_price' => (float) $item->base_price,
                ])->values(),
            ])
            ->values();

        return response()->json([
            'data' => $categories,
            'meta' => [
                'price_list_version' => (int) $this->settings->get('price_list_version', 1),
            ],
        ]);
    }
}
