<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Postavke su podaci u bazi. Ovaj zahtjev pazi da iz admina ne izadje
 * predlozak bez kljuca i da nigdje ne udje em dash.
 */
class UpdateSettingsRequest extends FormRequest
{
    /** Predlosci koje sistem zaista salje. Nijedan ne smije nestati. */
    public const TEMPLATE_KLJUCEVI = [
        'prijava_primljena',
        'termin_potvrdjen',
        'majstor_krenuo',
        'kasnjenje',
        'rok_probijen',
        'zavrseno',
        'pretplata_aktivna',
        'obnova_podsjetnik',
    ];

    /** Kljucevi koje admin smije mijenjati. */
    public const KLJUCEVI = [
        'radno_vrijeme',
        'satnica_redovna',
        'satnica_hitna',
        'izlazak_bez_pretplate',
        'ukljuceno_minuta',
        'materijal_marza_pct',
        'pro_volume_tiers',
        'notification_templates',
        'dispecer_email',
    ];

    /** Em dash je zabranjen u svakom tekstu koji ide korisniku. */
    private const EM_DASH = "\u{2014}";

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
            'radno_vrijeme' => ['nullable', 'array'],
            'radno_vrijeme.pon_pet.od' => ['required_with:radno_vrijeme', 'date_format:H:i'],
            'radno_vrijeme.pon_pet.do' => ['required_with:radno_vrijeme', 'date_format:H:i'],
            'radno_vrijeme.subota.od' => ['required_with:radno_vrijeme', 'date_format:H:i'],
            'radno_vrijeme.subota.do' => ['required_with:radno_vrijeme', 'date_format:H:i'],
            'radno_vrijeme.nedjelja.samo_hitno' => ['nullable', 'boolean'],
            'radno_vrijeme.napomena' => ['nullable', 'string', 'max:255'],

            'satnica_redovna' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'satnica_hitna' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'izlazak_bez_pretplate' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'ukljuceno_minuta' => ['nullable', 'integer', 'min:0', 'max:600'],
            'materijal_marza_pct' => ['nullable', 'integer', 'min:0', 'max:200'],

            'pro_volume_tiers' => ['nullable', 'array'],
            'pro_volume_tiers.*.min' => ['required', 'integer', 'min:1'],
            'pro_volume_tiers.*.max' => ['nullable', 'integer', 'min:1'],
            'pro_volume_tiers.*.pct' => ['required', 'integer', 'min:0', 'max:100'],

            'notification_templates' => ['nullable', 'array'],
            'notification_templates.*' => ['required', 'string', 'min:10', 'max:500'],

            'dispecer_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'radno_vrijeme.pon_pet.od.date_format' => 'Radno vrijeme se upisuje u obliku 08:00.',
            'radno_vrijeme.pon_pet.do.date_format' => 'Radno vrijeme se upisuje u obliku 18:00.',
            'radno_vrijeme.subota.od.date_format' => 'Radno vrijeme se upisuje u obliku 09:00.',
            'radno_vrijeme.subota.do.date_format' => 'Radno vrijeme se upisuje u obliku 14:00.',
            'notification_templates.*.required' => 'Predložak ne smije biti prazan.',
            'notification_templates.*.min' => 'Predložak je prekratak.',
            'pro_volume_tiers.*.pct.max' => 'Popust ne može biti veći od 100.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->provjeriPredloske($validator);
            $this->provjeriEmDash($validator);
        });
    }

    /**
     * Samo ono sto je stiglo, i samo kljucevi koje admin smije mijenjati.
     *
     * @return array<string, mixed>
     */
    public function izmjene(): array
    {
        $out = [];

        foreach (self::KLJUCEVI as $kljuc) {
            if ($this->has($kljuc) && $this->input($kljuc) !== null) {
                $out[$kljuc] = $this->input($kljuc);
            }
        }

        return $out;
    }

    private function provjeriPredloske(Validator $validator): void
    {
        $templates = $this->input('notification_templates');

        if (! is_array($templates)) {
            return;
        }

        foreach (self::TEMPLATE_KLJUCEVI as $kljuc) {
            if (! array_key_exists($kljuc, $templates)) {
                $validator->errors()->add(
                    'notification_templates',
                    'Nedostaje predložak '.$kljuc.'. Svi predlošci moraju ostati upisani.'
                );
            }
        }

        foreach (array_keys($templates) as $kljuc) {
            if (! in_array($kljuc, self::TEMPLATE_KLJUCEVI, true)) {
                $validator->errors()->add(
                    'notification_templates',
                    'Predložak '.$kljuc.' nije poznat sistemu.'
                );
            }
        }
    }

    /**
     * Em dash nikad ne ide korisniku, ni u jednom stringu.
     */
    private function provjeriEmDash(Validator $validator): void
    {
        foreach ($this->izmjene() as $kljuc => $vrijednost) {
            if ($this->imaEmDash($vrijednost)) {
                $validator->errors()->add(
                    $kljuc,
                    'Tekst sadrži crticu koju ne koristimo. Napišite rečenicu bez nje.'
                );
            }
        }
    }

    private function imaEmDash(mixed $vrijednost): bool
    {
        if (is_string($vrijednost)) {
            return str_contains($vrijednost, self::EM_DASH);
        }

        if (is_array($vrijednost)) {
            foreach ($vrijednost as $stavka) {
                if ($this->imaEmDash($stavka)) {
                    return true;
                }
            }
        }

        return false;
    }
}
