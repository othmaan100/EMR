<?php

namespace App\Imports;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Normalisers for messy legacy data. Each returns a clean value, or the
 * original value unchanged when it can't be understood (so validation
 * reports it with the row number).
 */
final class Values
{
    public const DATE_HELP = 'Date, e.g. 2024-01-31 or 31/01/2024.';

    /**
     * Any reasonable date → Y-m-d.
     */
    public static function date(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }
        // Excel serial day number.
        if (is_numeric($value) && (float) $value > 1 && (float) $value < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        $value = trim((string) $value);
        $formats = array_unique([setting('date_format', 'd/m/Y'), 'Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'Y/m/d', 'd M Y', 'j M Y', 'd F Y']);
        foreach ($formats as $format) {
            try {
                // Strict: re-formatting must give the same text, so 31/02/2024 is rejected, not rolled over.
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date && strcasecmp($date->format($format), $value) === 0 && $date->year > 1850) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                // try the next format
            }
        }

        return $value;
    }

    /**
     * "HH:MM" from 9:30, 09:30, 9.30, 0930, 9:30 AM or an Excel time fraction.
     */
    public static function time(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }
        if (is_numeric($value) && (float) $value < 1) {
            $minutes = (int) round((float) $value * 1440);

            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }
        foreach (['H:i', 'G:i', 'H.i', 'Hi', 'g:i A', 'g:iA', 'h:i A', 'H:i:s'] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, strtoupper(trim((string) $value)))->format('H:i');
            } catch (Throwable) {
            }
        }

        return $value;
    }

    /**
     * yes/no, true/false, 1/0, y/n → '1' / '0'.
     */
    public static function bool(mixed $value, ?bool $default = null): mixed
    {
        if ($value === null || $value === '') {
            return $default === null ? null : ($default ? '1' : '0');
        }
        $v = strtolower(trim((string) $value));

        return match (true) {
            in_array($v, ['1', 'yes', 'y', 'true', 't', 'active'], true) => '1',
            in_array($v, ['0', 'no', 'n', 'false', 'f', 'inactive'], true) => '0',
            default => $value,
        };
    }

    /**
     * Match a value against allowed options case-insensitively (keys or labels).
     *
     * @param  array<int|string, string>  $options  list or key => label
     */
    public static function option(mixed $value, array $options, array $aliases = []): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        $needle = strtolower(trim((string) $value));
        if (isset($aliases[$needle])) {
            return $aliases[$needle];
        }
        foreach ($options as $key => $label) {
            $candidate = is_int($key) ? $label : $key;
            if ($needle === strtolower((string) $candidate) || $needle === strtolower((string) $label)) {
                return $candidate;
            }
        }

        return $value;
    }

    /**
     * Excel drops the leading 0 of phone numbers stored as numbers
     * (08031234567 → 8031234567). Restore it for Nigerian mobiles.
     */
    public static function phone(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }
        if (setting('sms_country_code', '234') === '234' && preg_match('/^[789]\d{9}$/', $value)) {
            return '0'.$value;
        }

        return $value;
    }

    public static function upper(mixed $value): mixed
    {
        return is_string($value) ? strtoupper(trim($value)) : $value;
    }

    /**
     * Numbers written with thousands separators or currency symbols.
     */
    public static function number(mixed $value): mixed
    {
        if ($value === null || $value === '' || is_int($value) || is_float($value)) {
            return $value === '' ? null : $value;
        }
        $clean = preg_replace('/[^\d.\-]/', '', (string) $value);

        return is_numeric($clean) ? $clean : $value;
    }
}
