<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Key/value store for hospital-level configuration (name, logo, currency...).
 *
 * Values are cached forever and flushed on every write. Reads fall back to
 * config('emr.defaults') so the app renders sensibly before setup completes.
 */
class Settings
{
    public const CACHE_KEY = 'emr.settings';

    protected ?array $items = null;

    public function all(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        try {
            return $this->items = Cache::rememberForever(
                self::CACHE_KEY,
                fn () => Setting::query()->pluck('value', 'key')->all()
            );
        } catch (Throwable) {
            // Database not configured or not migrated yet (first run).
            return [];
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        if ($value === null || $value === '') {
            return $default ?? config("emr.defaults.$key");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>|string  $key
     */
    public function set(array|string $key, mixed $value = null): void
    {
        $values = is_array($key) ? $key : [$key => $value];

        foreach ($values as $k => $v) {
            Setting::query()->updateOrCreate(['key' => $k], ['value' => $v]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->items = null;
    }

    public function logoUrl(): ?string
    {
        $logo = $this->get('logo');

        return $logo ? asset($logo) : null;
    }
}
