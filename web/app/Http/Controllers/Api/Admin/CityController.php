<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CityStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Gradovi su podatak u bazi. Novi grad uvijek krece u pripremi, aktivira ga
 * dispecer tek kad na terenu ima majstora.
 */
class CityController extends Controller
{
    /** Granice BiH, gruba provjera da pin ne zavrsi u moru. */
    private const LAT_MIN = 42.0;

    private const LAT_MAX = 46.0;

    private const LNG_MIN = 15.0;

    private const LNG_MAX = 20.0;

    public function index(Request $request): JsonResponse
    {
        $cities = City::query()
            ->withCount('properties')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $cities->map(fn (City $city) => array_merge(
                (new CityResource($city))->toArray($request),
                ['properties_count' => (int) $city->properties_count]
            ))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->pravila(), $this->poruke());

        $city = City::create([
            'name' => (string) $validated['name'],
            'slug' => $this->slug((string) $validated['name']),
            'lat' => (float) $validated['lat'],
            'lng' => (float) $validated['lng'],
            // Novi grad se ne otvara odmah, prvo mora imati majstore.
            'status' => CityStatus::UPripremi,
        ]);

        return response()->json([
            'data' => new CityResource($city),
            'message' => 'Grad je dodan u stanju u pripremi.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $city = City::query()->find($id);

        if (! $city) {
            return response()->json(['message' => 'Grad nije pronađen.'], 404);
        }

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(CityStatus::values())],
            'name' => ['nullable', 'string', 'max:255', Rule::unique('cities', 'name')->ignore($city->id)],
            'lat' => ['nullable', 'numeric', 'between:'.self::LAT_MIN.','.self::LAT_MAX],
            'lng' => ['nullable', 'numeric', 'between:'.self::LNG_MIN.','.self::LNG_MAX],
        ], $this->poruke());

        $izmjene = [];

        if (isset($validated['status'])) {
            $izmjene['status'] = CityStatus::from($validated['status']);
        }

        if (isset($validated['name'])) {
            $izmjene['name'] = (string) $validated['name'];
            $izmjene['slug'] = $this->slug((string) $validated['name'], $city->id);
        }

        foreach (['lat', 'lng'] as $kljuc) {
            if (isset($validated[$kljuc])) {
                $izmjene[$kljuc] = (float) $validated[$kljuc];
            }
        }

        if ($izmjene !== []) {
            $city->update($izmjene);
        }

        return response()->json([
            'data' => new CityResource($city->refresh()),
            'message' => 'Grad je ažuriran.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $city = City::query()->withCount('properties')->find($id);

        if (! $city) {
            return response()->json(['message' => 'Grad nije pronađen.'], 404);
        }

        if ($city->properties_count > 0) {
            return response()->json([
                'message' => 'Grad ima upisane adrese pretplatnika i ne može se obrisati. Prebacite ga u pripremu.',
            ], 422);
        }

        $city->delete();

        return response()->json(['message' => 'Grad je obrisan.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function pravila(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('cities', 'name')],
            'lat' => ['required', 'numeric', 'between:'.self::LAT_MIN.','.self::LAT_MAX],
            'lng' => ['required', 'numeric', 'between:'.self::LNG_MIN.','.self::LNG_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function poruke(): array
    {
        return [
            'name.required' => 'Upišite naziv grada.',
            'name.unique' => 'Taj grad je već upisan.',
            'lat.required' => 'Upišite geografsku širinu.',
            'lat.between' => 'Geografska širina mora biti između 42 i 46, unutar granica BiH.',
            'lng.required' => 'Upišite geografsku dužinu.',
            'lng.between' => 'Geografska dužina mora biti između 15 i 20, unutar granica BiH.',
        ];
    }

    private function slug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $brojac = 2;

        while (City::query()->where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore))->exists()) {
            $slug = $base.'-'.$brojac++;
        }

        return $slug;
    }
}
