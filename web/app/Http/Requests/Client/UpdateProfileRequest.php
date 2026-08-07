<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mejl je ovdje samo za citanje, adresa se mijenja kroz zahtjev dispeceru.
 */
class UpdateProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'notifications' => ['nullable', 'array'],
            'notifications.push' => ['nullable', 'boolean'],
            'notifications.email' => ['nullable', 'boolean'],
            'notifications.marketing' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Unesite ime i prezime.',
        ];
    }

    /**
     * Prekidaci stizu i kao tekst, svodimo ih na logicku vrijednost.
     */
    protected function prepareForValidation(): void
    {
        $notifications = $this->input('notifications');

        if (! is_array($notifications)) {
            return;
        }

        foreach (['push', 'email', 'marketing'] as $kljuc) {
            if (! array_key_exists($kljuc, $notifications)) {
                continue;
            }

            $vrijednost = filter_var($notifications[$kljuc], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($vrijednost !== null) {
                $notifications[$kljuc] = $vrijednost;
            }
        }

        $this->merge(['notifications' => $notifications]);
    }
}
