<?php

use App\Support\Settings;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    /**
     * Read a hospital setting, e.g. setting('hospital_name').
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default);
    }
}

if (! function_exists('format_date')) {
    function format_date(mixed $date, bool $withTime = false): string
    {
        if (blank($date)) {
            return '';
        }

        $format = setting('date_format').($withTime ? ' h:i A' : '');

        return Carbon::parse($date)->format($format);
    }
}

if (! function_exists('money')) {
    function money(int|float|string|null $amount, bool $withSymbol = true): string
    {
        $formatted = number_format((float) $amount, 2);

        return $withSymbol ? setting('currency_symbol').$formatted : $formatted;
    }
}
