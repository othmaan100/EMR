<?php

namespace App\Imports;

use App\Models\Drug;
use App\Models\Prescription;
use Illuminate\Validation\Rule;

class DrugImporter extends CatalogImporter
{
    public function key(): string
    {
        return 'drugs';
    }

    public function title(): string
    {
        return 'Drug formulary';
    }

    public function group(): string
    {
        return 'Catalogues & prices';
    }

    public function description(): string
    {
        return 'Medicines doctors can prescribe and the pharmacy stocks.';
    }

    protected function model(): string
    {
        return Drug::class;
    }

    public function matchDescription(): string
    {
        return 'A drug is the same record when name + strength + form all match (e.g. "Paracetamol 500mg Tablet").';
    }

    public function rowKey(array $row): ?string
    {
        return implode('|', [$row['name'], $row['strength'], $row['form']]);
    }

    public function columns(): array
    {
        return [
            new ImportColumn('name', 'Generic name', true, ['string', 'max:150'], 'Amoxicillin', null, null, 'Artemether/Lumefantrine'),
            new ImportColumn('strength', 'Strength', false, ['string', 'max:50'], '500mg', null, null, '20/120mg'),
            new ImportColumn('form', 'Form', true, ['string', 'max:30'], 'Capsule', 'e.g. Tablet, Capsule, Syrup, Injection, Cream.', null, 'Tablet'),
            new ImportColumn('route', 'Default route', false, [Rule::in(Prescription::ROUTES)], 'Oral', null, Prescription::ROUTES, 'Oral'),
            new ImportColumn('dispensing_unit', 'Stock unit', false, ['string', 'max:30'], 'capsule', 'Unit stock is counted and dispensed in.', null, 'tablet'),
            new ImportColumn('reorder_level', 'Reorder level', false, ['integer', 'min:0', 'max:1000000'], '200', null, null, '120'),
            self::activeColumn(),
        ];
    }

    public function normalise(array $row): array
    {
        $row = parent::normalise($row);
        $row['route'] = Values::option($row['route'], Prescription::ROUTES, ['po' => 'Oral', 'by mouth' => 'Oral']);

        return $row;
    }

    public function find(array $row): mixed
    {
        return Drug::whereRaw('lower(name) = ?', [mb_strtolower($row['name'])])
            ->where(fn ($q) => $row['strength'] ? $q->whereRaw('lower(strength) = ?', [mb_strtolower($row['strength'])]) : $q->whereNull('strength')->orWhere('strength', ''))
            ->whereRaw('lower(form) = ?', [mb_strtolower($row['form'])])
            ->first();
    }

    protected function fields(): array
    {
        return ['name' => 'name', 'strength' => 'strength', 'form' => 'form', 'route' => 'route', 'dispensing_unit' => 'dispensing_unit',
            'reorder_level' => 'reorder_level', 'is_active' => 'is_active'];
    }
}
