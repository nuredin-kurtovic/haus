<?php

namespace App\Http\Requests;

use App\Enums\CityStatus;
use App\Enums\PaymentMethod;
use App\Enums\PropertyUse;
use App\Models\Package;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    private ?Package $paket = null;

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
            'package_id' => [
                'required',
                'integer',
                Rule::exists('packages', 'id')->where('active', true),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],

            'properties' => ['required', 'array', 'min:1', 'max:200'],
            'properties.*.city_id' => [
                'required',
                'integer',
                Rule::exists('cities', 'id')->where('status', CityStatus::Aktivan->value),
            ],
            'properties.*.street' => ['required', 'string', 'max:255'],
            'properties.*.use' => ['nullable', Rule::in(PropertyUse::values())],
            'properties.*.contact_name' => ['nullable', 'string', 'max:255'],
            'properties.*.contact_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'package_id.required' => 'Odaberite paket.',
            'package_id.exists' => 'Odabrani paket nije dostupan.',
            'name.required' => 'Unesite ime i prezime.',
            'email.required' => 'Unesite mejl adresu.',
            'email.email' => 'Mejl adresa nije ispravna.',
            'email.unique' => 'Ova mejl adresa je već registrovana. Prijavite se.',
            'password.required' => 'Unesite lozinku.',
            'password.min' => 'Lozinka mora imati najmanje 8 znakova.',
            'payment_method.required' => 'Odaberite način plaćanja.',
            'payment_method.in' => 'Način plaćanja može biti uplatnica ili kartica.',
            'properties.required' => 'Unesite adresu.',
            'properties.array' => 'Unesite adresu.',
            'properties.min' => 'Unesite najmanje jednu adresu.',
            'properties.*.city_id.required' => 'Odaberite grad.',
            'properties.*.city_id.exists' => 'U tom gradu još ne radimo. Odaberite grad sa liste.',
            'properties.*.street.required' => 'Unesite ulicu i broj.',
            'properties.*.use.in' => 'Namjena može biti zivim, izdaje_se ili prazan.',
        ];
    }

    /**
     * Pravila koja zavise od paketa: broj adresa po paketu.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $package = $this->paket();

            if (! $package) {
                return;
            }

            $properties = $this->input('properties');

            if (! is_array($properties)) {
                return;
            }

            $broj = count($properties);

            if (! $package->is_per_apartment && $broj !== 1) {
                $validator->errors()->add(
                    'properties',
                    'Paket '.$package->name.' pokriva jednu adresu. Unesite tačno jednu adresu.'
                );

                return;
            }

            if ($package->is_per_apartment && $broj < 2) {
                $validator->errors()->add(
                    'properties',
                    'Paket '.$package->name.' se plaća po stanu i traži najmanje 2 stana. Za jedan stan odaberite HAUS Mini ili HAUS Plus.'
                );
            }
        });
    }

    public function paket(): ?Package
    {
        if ($this->paket) {
            return $this->paket;
        }

        $id = $this->input('package_id');

        if (! is_numeric($id)) {
            return null;
        }

        return $this->paket = Package::query()->where('active', true)->find((int) $id);
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from((string) $this->input('payment_method'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function properties(): array
    {
        /** @var array<int, array<string, mixed>> $properties */
        $properties = $this->input('properties', []);

        return array_values($properties);
    }
}
