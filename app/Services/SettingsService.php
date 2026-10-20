<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'bariton_settings_all';

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::all()->keyBy('key')->toArray();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (! isset($all[$key])) {
            return $default;
        }

        $raw = $all[$key]['value'];
        $type = $all[$key]['type'] ?? 'string';

        return match ($type) {
            'integer' => (int) $raw,
            'float' => (float) $raw,
            'boolean' => (bool) $raw,
            'json' => is_array($raw) ? $raw : json_decode((string) $raw, true),
            default => (string) $raw,
        };
    }

    public function set(string $key, mixed $value): void
    {
        $setting = Setting::where('key', $key)->firstOrFail();
        $setting->update(['value' => $value]);
        $this->clearCache();
    }

    public function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::where('key', $key)->update(['value' => $value]);
        }
        $this->clearCache();
    }

    public function getPublic(): array
    {
        $all = $this->all();
        $public = [];

        foreach ($all as $key => $data) {
            if (! empty($data['is_public'])) {
                $public[$key] = $this->get($key);
            }
        }

        return $public;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
