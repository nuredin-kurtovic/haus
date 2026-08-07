<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PriceCategoryResource;
use App\Models\Package;
use App\Models\PriceCategory;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Objavljeni cjenovnik. Cijene po paketima se racunaju, nikad ne citaju iz baze.
     */
    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));

        $packages = Package::query()->active()->orderBy('sort')->get();

        $categories = PriceCategory::query()
            ->with(['items' => function ($items) use ($query) {
                $items->where('active', true)->orderBy('sort');

                if ($query !== '') {
                    $items->where('name', 'like', '%'.$query.'%');
                }
            }])
            ->when($category !== '', function ($builder) use ($category) {
                $builder->where(function ($where) use ($category) {
                    $where->where('slug', $category);

                    if (ctype_digit($category)) {
                        $where->orWhere('id', (int) $category);
                    }
                });
            })
            ->orderBy('sort')
            ->get()
            ->filter(fn (PriceCategory $model) => $model->items->isNotEmpty())
            ->values()
            ->map(fn (PriceCategory $model) => new PriceCategoryResource($model, $packages));

        return response()->json([
            'data' => $categories,
            'meta' => [
                'price_list_version' => (int) $this->settings->get('price_list_version', 1),
            ],
        ]);
    }
}
