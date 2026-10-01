<?php

namespace App\Imports;

use App\Models\Ward;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WardImporter extends CatalogImporter
{
    public function key(): string
    {
        return 'wards';
    }

    public function title(): string
    {
        return 'Wards & beds';
    }

    public function group(): string
    {
        return 'Organisation';
    }

    public function description(): string
    {
        return 'Inpatient wards and how many beds each has. Beds are numbered automatically (e.g. A1, A2…).';
    }

    public function dependsOn(): array
    {
        return ['departments'];
    }

    public function notes(): array
    {
        return [
            'Beds are only ever added, never removed: if a ward already has more beds than "number_of_beds", nothing changes.',
            'The daily bed charge can be left blank now and filled in later (import services, then re-import this file choosing "update").',
        ];
    }

    protected function model(): string
    {
        return Ward::class;
    }

    public function columns(): array
    {
        return [
            new ImportColumn('code', 'Code', true, ['alpha_dash', 'max:10'], 'FMW', null, null, 'PAED'),
            new ImportColumn('name', 'Name', true, ['string', 'max:150'], 'Female Medical Ward', null, null, 'Children\'s Ward'),
            new ImportColumn('type', 'Ward type', true, [Rule::in(Ward::TYPES)], 'Female Medical', null, Ward::TYPES, 'Paediatric'),
            new ImportColumn('gender', 'Gender', false, ['in:any,male,female'], 'female', 'any, male or female (default any).', ['any', 'male', 'female'], 'any'),
            new ImportColumn('number_of_beds', 'Number of beds', false, ['integer', 'min:0', 'max:500'], '20', null, null, '12'),
            new ImportColumn('bed_prefix', 'Bed label prefix', false, ['alpha_num', 'max:5'], 'F', 'Letters before bed numbers (default: first letter of the code).', null, 'P'),
            new ImportColumn('department_code', 'Department code', false, ['string'], 'MED'),
            new ImportColumn('bed_charge_service_code', 'Daily bed charge (service code)', false, ['string'], '', 'Code of an Accommodation service in the services catalogue.'),
            self::activeColumn(),
        ];
    }

    public function normalise(array $row): array
    {
        $row = parent::normalise($row);
        $row['type'] = Values::option($row['type'], Ward::TYPES);
        $row['gender'] = Values::option($row['gender'], ['any', 'male', 'female'], ['m' => 'male', 'f' => 'female', 'mixed' => 'any']);

        return $row;
    }

    public function check(array &$row): array
    {
        $errors = [];
        $row['_department_id'] = $this->lookup('department', $row['department_code']);
        if ($row['department_code'] && ! $row['_department_id']) {
            $errors[] = "Department \"{$row['department_code']}\" not found.";
        }
        $row['_service_id'] = $this->lookup('service', $row['bed_charge_service_code']);
        if ($row['bed_charge_service_code'] && ! $row['_service_id']) {
            $errors[] = "Service \"{$row['bed_charge_service_code']}\" not found — import services first.";
        }

        return $errors;
    }

    protected function fields(): array
    {
        return ['code' => 'code', 'name' => 'name', 'type' => 'type', 'gender' => 'gender', 'is_active' => 'is_active'];
    }

    protected function beforeSave(Model $model, array $row, bool $updating): void
    {
        $model->gender ??= 'any';
        if ($row['_department_id']) {
            $model->department_id = $row['_department_id'];
        }
        if ($row['_service_id']) {
            $model->service_id = $row['_service_id'];
        }
    }

    protected function afterSave(Model $model, array $row, bool $updating): void
    {
        $wanted = (int) ($row['number_of_beds'] ?? 0);
        $prefix = Str::upper($row['bed_prefix'] ?: substr($model->code, 0, 1));
        $existing = $model->beds()->pluck('label')->all();
        $n = 1;
        for ($i = count($existing); $i < $wanted; $i++) {
            while (in_array($prefix.$n, $existing, true)) {
                $n++;
            }
            $model->beds()->create(['label' => $prefix.$n]);
            $existing[] = $prefix.$n;
        }
    }
}
