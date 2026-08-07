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
        return $this->cjenovnik($request, null);
    }

    /**
     * Isti cjenovnik, plus my_price po paketu prijavljenog klijenta.
     *
     * Klijent bez pretplate vidi osnovnu cijenu kao svoju, jer popusta nema.
     */
    public function mine(Request $request): JsonResponse
    {
        $subscription = $request->user()->currentSubscription();

        return $this->cjenovnik($request, $subscription?->package);
    }

    private function cjenovnik(Request $request, ?Package $myPackage): JsonResponse
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
            ->map(fn (PriceCategory $model) => new PriceCategoryResource($model, $packages, $myPackage));

        $meta = [
            'price_list_version' => (int) $this->settings->get('price_list_version', 1),
        ];

        if ($myPackage) {
            $meta['my_package'] = [
                'name' => $myPackage->name,
                'slug' => $myPackage->slug,
                'labor_discount_pct' => (int) $myPackage->labor_discount_pct,
                'material_discount_pct' => (int) $myPackage->material_discount_pct,
            ];
        }

        return response()->json([
            'data' => $categories,
            'meta' => $meta,
        ]);
    }
}
