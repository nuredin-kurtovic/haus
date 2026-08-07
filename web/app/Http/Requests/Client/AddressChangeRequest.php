<?php

namespace App\Http\Requests\Client;

use App\Models\SubscriptionProperty;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Zahtjev za promjenu adrese. Adresu mijenja dispecer, ne klijent sam.
 */
class AddressChangeRequest extends FormRequest
{
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
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'subscription_property_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Napišite koju adresu mijenjate i na koju.',
            'message.min' => 'Napišite najmanje 10 znakova.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $id = $this->input('subscription_property_id');

            if (($id === null || $id === '') || $this->stan() !== null) {
                return;
            }

            $validator->errors()->add(
                'subscription_property_id',
                'Odabrana adresa ne pripada vašoj pretplati.'
            );
        });
    }

    /**
     * Adresa na koju se zahtjev odnosi. Bez izbora ide prva adresa pretplate.
     */
    public function stan(): ?SubscriptionProperty
    {
        $pretplata = $this->user()?->currentSubscription();

        if (! $pretplata) {
            return null;
        }

        $stanovi = $pretplata->properties()->with('city')->get();
        $id = $this->input('subscription_property_id');

        if ($id === null || $id === '') {
            return $stanovi->first();
        }

        return $stanovi->firstWhere('id', (int) $id);
    }
}
