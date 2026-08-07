<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Zatvaranje naloga. Isti zahtjev koristi i majstor i dispecer.
 *
 * Stize kao multipart, pa liste stavki mogu doci i kao JSON tekst.
 */
class CompleteJobRequest extends FormRequest
{
    /** Fotografija sa telefona ide do 8 MB. */
    private const MAX_KB = 8192;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'findings' => ['required', 'string', 'min:10', 'max:5000'],

            'items' => ['nullable', 'array', 'max:50'],
            'items.*.price_item_id' => [
                'required',
                'integer',
                Rule::exists('price_items', 'id')->where('active', true),
            ],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],

            'materials' => ['nullable', 'array', 'max:50'],
            'materials.*.name' => ['required', 'string', 'max:255'],
            'materials.*.purchase_price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'materials.*.qty' => ['required', 'numeric', 'min:0.01', 'max:9999'],

            'photos_before' => ['required', 'array', 'min:1', 'max:10'],
            'photos_before.*' => ['image', 'max:'.self::MAX_KB],
            'photos_after' => ['required', 'array', 'min:1', 'max:10'],
            'photos_after.*' => ['image', 'max:'.self::MAX_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'findings.required' => 'Upišite nalaz.',
            'findings.min' => 'Nalaz mora imati najmanje 10 znakova.',
            'items.*.price_item_id.required' => 'Odaberite poziciju iz cjenovnika.',
            'items.*.price_item_id.exists' => 'Pozicija iz cjenovnika ne postoji.',
            'items.*.qty.required' => 'Upišite količinu.',
            'items.*.qty.min' => 'Količina mora biti najmanje 1.',
            'materials.*.name.required' => 'Upišite naziv materijala.',
            'materials.*.purchase_price.required' => 'Upišite nabavnu cijenu materijala.',
            'materials.*.purchase_price.numeric' => 'Nabavna cijena mora biti broj.',
            'materials.*.qty.required' => 'Upišite količinu materijala.',
            'photos_before.required' => 'Dodajte najmanje jednu fotografiju prije.',
            'photos_before.min' => 'Dodajte najmanje jednu fotografiju prije.',
            'photos_before.*.image' => 'Prilog mora biti fotografija.',
            'photos_before.*.max' => 'Fotografija može biti najviše 8 MB.',
            'photos_after.required' => 'Dodajte najmanje jednu fotografiju poslije.',
            'photos_after.min' => 'Dodajte najmanje jednu fotografiju poslije.',
            'photos_after.*.image' => 'Prilog mora biti fotografija.',
            'photos_after.*.max' => 'Fotografija može biti najviše 8 MB.',
        ];
    }

    /**
     * Multipart nosi liste kao JSON tekst, pa ih ovdje raspakujemo.
     */
    protected function prepareForValidation(): void
    {
        foreach (['items', 'materials'] as $kljuc) {
            $vrijednost = $this->input($kljuc);

            if (! is_string($vrijednost)) {
                continue;
            }

            $dekodirano = json_decode($vrijednost, true);

            $this->merge([$kljuc => is_array($dekodirano) ? $dekodirano : []]);
        }
    }

    /**
     * @return array<int, array{price_item_id: int, qty: int}>
     */
    public function stavke(): array
    {
        /** @var array<int, array{price_item_id: int|string, qty: int|string}> $items */
        $items = $this->input('items', []) ?? [];

        return array_values(array_map(fn (array $red): array => [
            'price_item_id' => (int) $red['price_item_id'],
            'qty' => (int) $red['qty'],
        ], $items));
    }

    /**
     * @return array<int, array{name: string, purchase_price: float, qty: float}>
     */
    public function materijali(): array
    {
        /** @var array<int, array{name: string, purchase_price: int|float|string, qty: int|float|string}> $materials */
        $materials = $this->input('materials', []) ?? [];

        return array_values(array_map(fn (array $red): array => [
            'name' => (string) $red['name'],
            'purchase_price' => (float) $red['purchase_price'],
            'qty' => (float) $red['qty'],
        ], $materials));
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function fotografijePrije(): array
    {
        return $this->fotografije('photos_before');
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function fotografijePoslije(): array
    {
        return $this->fotografije('photos_after');
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function fotografije(string $kljuc): array
    {
        $files = $this->file($kljuc, []);

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return array_values(array_filter(
            is_array($files) ? $files : [],
            fn ($file) => $file instanceof UploadedFile
        ));
    }
}
