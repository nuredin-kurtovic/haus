<?php

namespace App\Http\Requests;

use App\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRequest extends FormRequest
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
            'fcm_token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(DevicePlatform::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fcm_token.required' => 'Token uređaja nedostaje.',
            'platform.required' => 'Platforma uređaja nedostaje.',
            'platform.in' => 'Platforma može biti ios ili android.',
        ];
    }
}
