<?php

namespace App\Imports;

use App\Models\ImagingTest;

class ImagingTestImporter extends CatalogImporter
{
    public function key(): string
    {
        return 'imaging';
    }

    public function title(): string
    {
        return 'Imaging examinations';
    }

    public function group(): string
    {
        return 'Catalogues & prices';
    }

    public function description(): string
    {
        return 'X-ray, ultrasound, CT and other examinations doctors can request.';
    }

    protected function model(): string
    {
        return ImagingTest::class;
    }

    public function columns(): array
    {
        return [
            new ImportColumn('code', 'Code', true, ['alpha_dash', 'max:20'], 'XR-KNEE', null, null, 'US-PELV'),
            new ImportColumn('name', 'Name', true, ['string', 'max:150'], 'X-ray knee (AP/lateral)', null, null, 'Pelvic ultrasound'),
            new ImportColumn('modality', 'Modality', true, ['string', 'max:30'], 'X-ray', 'e.g. X-ray, Ultrasound, CT, MRI, Mammography.', null, 'Ultrasound'),
            new ImportColumn('report_template', 'Normal report template', false, ['string', 'max:5000'], '', 'Optional text that pre-fills the findings.'),
            self::activeColumn(),
        ];
    }

    protected function fields(): array
    {
        return ['code' => 'code', 'name' => 'name', 'modality' => 'modality', 'report_template' => 'report_template', 'is_active' => 'is_active'];
    }
}
