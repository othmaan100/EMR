<?php

namespace App\Imports;

use App\Models\InsuranceProvider;
use Illuminate\Database\Eloquent\Model;

class InsuranceProviderImporter extends CatalogImporter
{
    public function key(): string
    {
        return 'insurers';
    }

    public function title(): string
    {
        return 'Insurance, HMOs & companies';
    }

    public function group(): string
    {
        return 'Organisation';
    }

    public function description(): string
    {
        return 'HMOs, NHIA schemes and corporate clients that pay for patients. Patients are linked to these by code.';
    }

    protected function model(): string
    {
        return InsuranceProvider::class;
    }

    public function columns(): array
    {
        return [
            new ImportColumn('code', 'Code', true, ['alpha_dash', 'max:20'], 'HYG', null, null, 'NHIA'),
            new ImportColumn('name', 'Name', true, ['string', 'max:150'], 'Hygeia HMO', null, null, 'National Health Insurance Authority'),
            new ImportColumn('type', 'Type', false, ['in:insurance,corporate'], 'insurance', 'insurance or corporate (default insurance).', ['insurance', 'corporate'], 'insurance'),
            new ImportColumn('coverage_percent', 'Coverage %', false, ['integer', 'between:0,100'], '100', 'Share of each bill the payer covers (default 100).', null, '90'),
            new ImportColumn('requires_pa_code', 'Requires PA code', false, ['in:0,1'], 'no', 'yes = every claim needs a pre-authorisation code.', ['yes', 'no'], 'yes'),
            new ImportColumn('contact_person', 'Contact person', false, ['string', 'max:150']),
            new ImportColumn('phone', 'Phone', false, ['string', 'max:30'], '0800 000 0000'),
            new ImportColumn('email', 'Email', false, ['email', 'max:150']),
            new ImportColumn('address', 'Address', false, ['string', 'max:255']),
            self::activeColumn(),
        ];
    }

    public function normalise(array $row): array
    {
        $row = parent::normalise($row);
        $row['type'] = Values::option($row['type'], ['insurance', 'corporate'], ['hmo' => 'insurance', 'company' => 'corporate', 'retainer' => 'corporate']);
        $row['requires_pa_code'] = Values::bool($row['requires_pa_code']);
        $row['coverage_percent'] = Values::number(is_string($row['coverage_percent']) ? rtrim($row['coverage_percent'], '%') : $row['coverage_percent']);

        return $row;
    }

    protected function fields(): array
    {
        return ['code' => 'code', 'name' => 'name', 'type' => 'type', 'coverage_percent' => 'coverage_percent', 'requires_pa_code' => 'requires_authorization',
            'contact_person' => 'contact_person', 'phone' => 'phone', 'email' => 'email', 'address' => 'address', 'is_active' => 'is_active'];
    }

    protected function beforeSave(Model $model, array $row, bool $updating): void
    {
        $model->type ??= 'insurance';
        $model->coverage_percent ??= 100;
        $model->requires_authorization ??= false;
    }
}
