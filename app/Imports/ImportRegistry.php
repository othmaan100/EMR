<?php

namespace App\Imports;

use Illuminate\Support\Collection;

/**
 * All import types, in the order they should normally be run.
 */
final class ImportRegistry
{
    /** @var list<class-string<Importer>> */
    public const IMPORTERS = [
        // Organisation first: everything else refers to it.
        DepartmentImporter::class,
        InsuranceProviderImporter::class,
        ClinicImporter::class,
        WardImporter::class,
        SupplierImporter::class,
        // People
        StaffImporter::class,
        PatientImporter::class,
        // Catalogues & prices
        ServiceImporter::class,
        LabTestImporter::class,
        ImagingTestImporter::class,
        DrugImporter::class,
        ProcedureImporter::class,
        StoreItemImporter::class,
        PriceImporter::class,
        // Opening balances & history
        DrugStockImporter::class,
        PatientBalanceImporter::class,
        ImmunizationImporter::class,
        AppointmentImporter::class,
    ];

    /**
     * @return Collection<string, Importer>
     */
    public static function all(): Collection
    {
        return collect(self::IMPORTERS)->map(fn ($class) => new $class)->keyBy(fn (Importer $i) => $i->key());
    }

    public static function find(string $key): ?Importer
    {
        return self::all()->get($key);
    }

    public static function make(string $key): Importer
    {
        return self::find($key) ?? abort(404);
    }

    /**
     * Position in the recommended order (1-based), for "Step n" labels.
     */
    public static function step(string $key): int
    {
        return (int) array_search($key, self::all()->keys()->all(), true) + 1;
    }
}
