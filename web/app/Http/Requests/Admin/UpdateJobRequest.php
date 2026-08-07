<?php

namespace App\Http\Requests\Admin;

use App\Enums\JobStatus;
use App\Models\Technician;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Dodjela majstora, termin i tranzicija stanja. Domenska pravila (prozor od
 * 2 sata, radno vrijeme, dozvoljene tranzicije) su u JobTransitionService.
 */
class UpdateJobRequest extends FormRequest
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
            'technician_id' => [
                'nullable',
                'integer',
                Rule::exists('technicians', 'id')->where('active', true),
            ],
            'scheduled_window_start' => ['nullable', 'date'],
            'scheduled_window_end' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(JobStatus::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'technician_id.exists' => 'Odabrani majstor nije aktivan.',
            'scheduled_window_start.date' => 'Početak prozora nije ispravan datum.',
            'scheduled_window_end.date' => 'Kraj prozora nije ispravan datum.',
            'status.in' => 'Stanje naloga nije poznato.',
        ];
    }

    public function majstor(): ?Technician
    {
        $id = $this->input('technician_id');

        if ($id === null || $id === '') {
            return null;
        }

        return Technician::query()->find((int) $id);
    }

    public function pocetak(): ?Carbon
    {
        return $this->trenutak('scheduled_window_start');
    }

    public function kraj(): ?Carbon
    {
        return $this->trenutak('scheduled_window_end');
    }

    public function stanje(): ?JobStatus
    {
        $status = $this->input('status');

        return $status ? JobStatus::tryFrom((string) $status) : null;
    }

    private function trenutak(string $kljuc): ?Carbon
    {
        $value = $this->input($kljuc);

        return ($value === null || $value === '') ? null : Carbon::parse((string) $value);
    }
}
