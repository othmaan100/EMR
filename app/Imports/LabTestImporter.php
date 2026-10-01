<?php

namespace App\Imports;

use App\Models\LabTest;

class LabTestImporter extends CatalogImporter
{
    public function key(): string
    {
        return 'lab-tests';
    }

    public function title(): string
    {
        return 'Laboratory tests';
    }

    public function group(): string
    {
        return 'Catalogues & prices';
    }

    public function description(): string
    {
        return 'Tests doctors can order. Result fields and reference ranges are then set per test under Catalogues → Lab Tests.';
    }

    public function notes(): array
    {
        return ['Tests without result fields accept a free-text result; add structured fields and normal ranges afterwards on each test.'];
    }

    protected function model(): string
    {
        return LabTest::class;
    }

    public function columns(): array
    {
        return [
            new ImportColumn('code', 'Code', true, ['alpha_dash', 'max:20'], 'LFT', null, null, 'WIDAL'),
            new ImportColumn('name', 'Name', true, ['string', 'max:150'], 'Liver function test', null, null, 'Widal test'),
            new ImportColumn('category', 'Category', true, ['string', 'max:50'], 'Chemistry', 'e.g. Haematology, Chemistry, Microbiology, Serology.', null, 'Serology'),
            new ImportColumn('sample_type', 'Sample type', false, ['string', 'max:50'], 'Blood (plain tube)', null, null, 'Blood'),
            new ImportColumn('turnaround_hours', 'Turnaround (hours)', false, ['integer', 'min:0', 'max:2000'], '24', null, null, '4'),
            self::activeColumn(),
        ];
    }

    protected function fields(): array
    {
        return ['code' => 'code', 'name' => 'name', 'category' => 'category', 'sample_type' => 'sample_type', 'turnaround_hours' => 'turnaround_hours', 'is_active' => 'is_active'];
    }
}
