<?php

namespace App\Imports;

use App\Models\Department;
use Illuminate\Validation\Rule;

class DepartmentImporter extends CatalogImporter
{
    public function key(): string
    {
        return 'departments';
    }

    public function title(): string
    {
        return 'Departments & units';
    }

    public function group(): string
    {
        return 'Organisation';
    }

    public function description(): string
    {
        return 'Clinical and non-clinical departments. Staff, clinics and wards are linked to these by code.';
    }

    protected function model(): string
    {
        return Department::class;
    }

    public function columns(): array
    {
        $types = config('emr.department_types');

        return [
            new ImportColumn('code', 'Code', true, ['alpha_dash', 'max:20'], 'MED', 'Short unique code.', null, 'LAB'),
            new ImportColumn('name', 'Name', true, ['string', 'max:150'], 'Internal Medicine', null, null, 'Laboratory Services'),
            new ImportColumn('type', 'Type', true, [Rule::in($types)], 'Clinical', null, $types, 'Diagnostic'),
            new ImportColumn('location', 'Location', false, ['string', 'max:150'], 'Block A'),
            new ImportColumn('phone_extension', 'Phone extension', false, ['string', 'max:20'], '201'),
            new ImportColumn('description', 'Description', false, ['string', 'max:1000']),
            self::activeColumn(),
        ];
    }

    public function normalise(array $row): array
    {
        $row = parent::normalise($row);
        $row['type'] = Values::option($row['type'], config('emr.department_types'));

        return $row;
    }

    protected function fields(): array
    {
        return ['code' => 'code', 'name' => 'name', 'type' => 'type', 'location' => 'location',
            'phone_extension' => 'phone_extension', 'description' => 'description', 'is_active' => 'is_active'];
    }
}
