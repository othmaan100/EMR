<?php

namespace App\Imports;

use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ServiceImporter extends CatalogImporter
{
    public function key(): string
    {
        return 'services';
    }

    public function title(): string
    {
        return 'Billable services';
    }

    public function group(): string
    {
        return 'Catalogues & prices';
    }

    public function description(): string
    {
        return 'Fees such as registration, consultation, procedures, nursing care and bed charges. Set their prices with the Prices import.';
    }

    public function dependsOn(): array
    {
        return ['clinics'];
    }

    protected function model(): string
    {
        return Service::class;
    }

    public function columns(): array
    {
        return [
            new ImportColumn('code', 'Code', true, ['alpha_dash', 'max:20'], 'DRESS', null, null, 'BED-GEN'),
            new ImportColumn('name', 'Name', true, ['string', 'max:150'], 'Wound dressing', null, null, 'General ward bed (per day)'),
            new ImportColumn('category', 'Category', true, [Rule::in(Service::CATEGORIES)], 'Nursing', null, Service::CATEGORIES, 'Accommodation'),
            new ImportColumn('clinic_code', 'Consultation fee for clinic', false, ['string'], '', 'Only for a clinic-specific consultation fee: the clinic code.'),
            self::activeColumn(),
        ];
    }

    public function normalise(array $row): array
    {
        $row = parent::normalise($row);
        $row['category'] = Values::option($row['category'], Service::CATEGORIES);

        return $row;
    }

    public function check(array &$row): array
    {
        $row['_clinic_id'] = $this->lookup('clinic', $row['clinic_code']);

        return $row['clinic_code'] && ! $row['_clinic_id'] ? ["Clinic \"{$row['clinic_code']}\" not found."] : [];
    }

    protected function fields(): array
    {
        return ['code' => 'code', 'name' => 'name', 'category' => 'category', 'is_active' => 'is_active'];
    }

    protected function beforeSave(Model $model, array $row, bool $updating): void
    {
        if ($row['_clinic_id']) {
            $model->clinic_id = $row['_clinic_id'];
        }
    }

    protected function afterSave(Model $model, array $row, bool $updating): void
    {
        $this->remember('service', $model->code, $model->id);
    }
}
