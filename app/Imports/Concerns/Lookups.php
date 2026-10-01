<?php

namespace App\Imports\Concerns;

use App\Models\Clinic;
use App\Models\Department;
use App\Models\Drug;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\Service;
use App\Models\StoreItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vaccine;

/**
 * Cached code → id lookups, so large files don't query per row.
 */
trait Lookups
{
    /** @var array<string, array<string, int>> */
    protected array $maps = [];

    protected function lookup(string $map, ?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $this->maps[$map] ??= match ($map) {
            'department' => Department::pluck('id', 'code')->all(),
            'clinic' => Clinic::pluck('id', 'code')->all(),
            'insurer' => InsuranceProvider::pluck('id', 'code')->all(),
            'service' => Service::pluck('id', 'code')->all(),
            'store_item' => StoreItem::pluck('id', 'code')->all(),
            'vaccine' => Vaccine::pluck('id', 'code')->all(),
            'supplier' => Supplier::pluck('id', 'name')->all(),
            'user' => User::pluck('id', 'username')->all(),
        };

        $key = mb_strtolower(trim($value));
        foreach ($this->maps[$map] as $code => $id) {
            if (mb_strtolower((string) $code) === $key) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Newly created records become visible to later rows of the same file.
     */
    protected function remember(string $map, string $code, int $id): void
    {
        if (isset($this->maps[$map])) {
            $this->maps[$map][$code] = $id;
        }
    }

    protected function patientId(?string $hospitalNumber): ?int
    {
        if (! $hospitalNumber) {
            return null;
        }

        return Patient::where('hospital_number', $hospitalNumber)->value('id')
            ?? Patient::where('legacy_number', $hospitalNumber)->value('id');
    }

    /**
     * A drug by its full label ("Paracetamol 500mg Tablet") or, if unique, its name.
     */
    protected function drugId(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        $this->maps['drug'] ??= Drug::get()->mapWithKeys(fn (Drug $d) => [mb_strtolower($d->label) => $d->id])->all();
        $key = mb_strtolower(preg_replace('/\s+/', ' ', trim($value)));
        if (isset($this->maps['drug'][$key])) {
            return $this->maps['drug'][$key];
        }

        $byName = Drug::whereRaw('lower(name) = ?', [$key])->pluck('id');

        return $byName->count() === 1 ? $byName->first() : null;
    }
}
