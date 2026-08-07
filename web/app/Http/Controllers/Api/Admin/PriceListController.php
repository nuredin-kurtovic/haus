<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PriceCategory;
use App\Models\PriceItem;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cjenovnik ima objavljenu cijenu i nacrt. Objava kopira nacrte u objavljeno
 * u jednoj transakciji, pa web i mobile dobiju novu cijenu istog trena.
 */
class PriceListController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Sve pozicije, sa nacrtom i oznakom neobjavljene izmjene.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = PriceCategory::query()
            ->with(['items' => fn ($items) => $items->orderBy('sort')])
            ->orderBy('sort')
            ->get();

        $dirty = 0;

        $data = $categories->map(function (PriceCategory $category) use (&$dirty): array {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'items' => $category->items->map(function (PriceItem $item) use (&$dirty): array {
                    $izmijenjen = $item->hasUnpublishedChange();

                    if ($izmijenjen) {
                        $dirty++;
                    }

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'unit' => $item->unit,
                        'active' => (bool) $item->active,
                        'base_price' => (float) $item->base_price,
                        'draft_base_price' => $item->draft_base_price !== null ? (float) $item->draft_base_price : null,
                        'dirty' => $izmijenjen,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'price_list_version' => (int) $this->settings->get('price_list_version', 1),
                'dirty_count' => $dirty,
            ],
        ]);
    }

    /**
     * Nacrt nove cijene. Objavljena cijena se ovim ne dira.
     */
    public function updateItem(Request $request, int $id): JsonResponse
    {
        $item = PriceItem::query()->find($id);

        if (! $item) {
            return response()->json(['message' => 'Pozicija nije pronađena.'], 404);
        }

        $validated = $request->validate([
            'draft_base_price' => ['required', 'numeric', 'min:0', 'max:99999'],
        ], [
            'draft_base_price.required' => 'Upišite novu cijenu.',
            'draft_base_price.numeric' => 'Cijena mora biti broj.',
            'draft_base_price.min' => 'Cijena ne može biti negativna.',
        ]);

        $item->update(['draft_base_price' => (float) $validated['draft_base_price']]);

        return response()->json([
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'base_price' => (float) $item->base_price,
                'draft_base_price' => (float) $item->draft_base_price,
                'dirty' => $item->hasUnpublishedChange(),
            ],
            'message' => 'Nacrt cijene je sačuvan. Objavite cjenovnik kad završite.',
        ]);
    }

    /**
     * Objava svih nacrta odjednom, uz podizanje verzije cjenovnika.
     */
    public function publish(Request $request): JsonResponse
    {
        $objavljeno = DB::transaction(function (): int {
            $items = PriceItem::query()
                ->whereNotNull('draft_base_price')
                ->lockForUpdate()
                ->get();

            $broj = 0;

            foreach ($items as $item) {
                if ($item->hasUnpublishedChange()) {
                    $broj++;
                }

                $item->update([
                    'base_price' => $item->draft_base_price,
                    'draft_base_price' => null,
                ]);
            }

            return $broj;
        });

        $verzija = (int) $this->settings->get('price_list_version', 1) + 1;
        $this->settings->set('price_list_version', $verzija);

        return response()->json([
            'data' => [
                'published' => $objavljeno,
                'price_list_version' => $verzija,
            ],
            'message' => $objavljeno === 0
                ? 'Nije bilo izmjena za objavu.'
                : 'Objavljeno izmjena: '.$objavljeno.'.',
        ]);
    }
}
