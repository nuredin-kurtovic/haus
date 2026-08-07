<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Postavke su podaci u bazi, uredjivane iz admin panela. Nikad hardkodirane.
 */
class SettingsService
{
    private const CACHE_PREFIX = 'haus.setting.';

    private const CACHE_TTL = 3600;

    public function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::remember(
            self::CACHE_PREFIX.$key,
            self::CACHE_TTL,
            fn () => Setting::query()->where('key', $key)->value('value')
        );

        return $value ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    public function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * Predlozak obavjestenja po kljucu, sa zamijenjenim placeholderima.
     *
     * @param  array<string, string|int>  $replacements
     */
    public function renderTemplate(string $templateKey, array $replacements = []): ?string
    {
        $templates = $this->get('notification_templates', []);

        if (! is_array($templates) || ! isset($templates[$templateKey])) {
            return null;
        }

        $body = (string) $templates[$templateKey];

        foreach ($replacements as $needle => $value) {
            $body = str_replace('{'.$needle.'}', (string) $value, $body);
        }

        return $body;
    }
}
