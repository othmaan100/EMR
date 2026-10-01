<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Concurrency-safe counters. Two receptionists registering at the same
 * moment will never receive the same number (row lock inside a transaction).
 */
class Sequence
{
    public static function next(string $name): int
    {
        return DB::transaction(function () use ($name) {
            DB::table('sequences')->insertOrIgnore(['name' => $name, 'next_value' => 1]);

            $value = DB::table('sequences')->where('name', $name)->lockForUpdate()->value('next_value');
            DB::table('sequences')->where('name', $name)->update(['next_value' => $value + 1]);

            return (int) $value;
        });
    }

    /**
     * Make sure the counter will hand out numbers above $value
     * (used when legacy numbers are imported in our own format).
     */
    public static function ensureAbove(string $name, int $value): void
    {
        DB::transaction(function () use ($name, $value) {
            DB::table('sequences')->insertOrIgnore(['name' => $name, 'next_value' => 1]);
            DB::table('sequences')->where('name', $name)->where('next_value', '<=', $value)->lockForUpdate()->update(['next_value' => $value + 1]);
        });
    }

    public static function patientNumber(): string
    {
        $number = str_pad((string) self::next('patient'), (int) setting('patient_number_padding'), '0', STR_PAD_LEFT);

        return setting('patient_number_prefix').'-'.$number;
    }
}
