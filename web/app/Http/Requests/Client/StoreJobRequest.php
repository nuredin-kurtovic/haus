<?php

namespace App\Http\Requests\Client;

use App\Models\PriceCategory;
use App\Models\Subscription;
use App\Models\SubscriptionProperty;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Prijava kvara. Aktivnu pretplatu je vec provjerio middleware subscription.active.
 */
class StoreJobRequest extends FormRequest
{
    private ?Subscription $pretplata = null;

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
            'price_category_id' => ['required', 'integer', Rule::exists('price_categories', 'id')],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'is_emergency' => ['required', 'boolean'],
            'preferred_window' => ['nullable', 'string', 'max:255'],
            'subscription_property_id' => ['nullable', 'integer'],
            // 8 MB je gornja granica fotografije sa telefona.
            'photo' => ['nullable', 'image', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price_category_id.required' => 'Odaberite vrstu kvara.',
            'price_category_id.exists' => 'Odabrana vrsta kvara ne postoji.',
            'description.required' => 'Opišite kvar.',
            'description.min' => 'Opišite kvar sa najmanje 10 znakova.',
            'is_emergency.required' => 'Odaberite da li je kvar hitan.',
            'is_emergency.boolean' => 'Hitnost može biti samo da ili ne.',
            'photo.image' => 'Prilog mora biti fotografija.',
            'photo.max' => 'Fotografija može biti najviše 8 MB.',
        ];
    }

    /**
     * Multipart salje logicke vrijednosti kao tekst, pa ih ovdje svodimo na bool.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('is_emergency')) {
            return;
        }

        $vrijednost = filter_var($this->input('is_emergency'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($vrijednost !== null) {
            $this->merge(['is_emergency' => $vrijednost]);
        }
    }

    /**
     * Adresa mora pripadati pretplati. Pro sa vise stanova mora reci koji je.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pretplata = $this->pretplata();

            if (! $pretplata) {
                return;
            }

            $stanovi = $pretplata->properties;
            $id = $this->input('subscription_property_id');

            if ($id === null || $id === '') {
                if ($stanovi->count() > 1) {
                    $validator->errors()->add(
                        'subscription_property_id',
                        'Odaberite stan na koji se prijava odnosi.'
                    );
                }

                return;
            }

            if (! $stanovi->firstWhere('id', (int) $id)) {
                $validator->errors()->add(
                    'subscription_property_id',
                    'Odabrana adresa ne pripada vašoj pretplati.'
                );
            }
        });
    }

    public function pretplata(): ?Subscription
    {
        if ($this->pretplata) {
            return $this->pretplata;
        }

        $pretplata = $this->user()?->activeSubscription()->with(['package', 'properties'])->first();

        return $this->pretplata = $pretplata;
    }

    /**
     * Jedna adresa se uzima sama, vise njih trazi izbor. Izbor je vec validiran.
     */
    public function stan(): SubscriptionProperty
    {
        $stanovi = $this->pretplata()->properties;
        $id = $this->input('subscription_property_id');

        if ($id === null || $id === '') {
            return $stanovi->first();
        }

        return $stanovi->firstWhere('id', (int) $id);
    }

    public function kategorija(): PriceCategory
    {
        return PriceCategory::query()->findOrFail((int) $this->input('price_category_id'));
    }

    public function jeHitno(): bool
    {
        return (bool) $this->boolean('is_emergency');
    }
}
