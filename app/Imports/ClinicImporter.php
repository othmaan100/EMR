<?php

namespace App\Imports;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ClinicImporter extends CatalogImporter
{
    public function key(): string
    {
        return 'clinics';
    }

    public function title(): string
    {
        return 'Clinics';
    }

    public function group(): string
    {
        return 'Organisation';
    }

    public function description(): string
    {
        return 'Outpatient clinics patients are checked into and booked for.';
    }

    public function dependsOn(): array
    {
        return ['departments'];
    }

    protected function model(): string
    {
        return Clinic::class;
    }

    public function columns(): array
    {
        $types = array_keys(config('emr.specialty.clinic_types'));

        return [
            new ImportColumn('code', 'Code', true, ['alpha_dash', 'max:10'], 'GOPD', 'Up to 10 letters; used in queue numbers (GOPD-001).', null, 'EYE'),
            new ImportColumn('name', 'Name', true, ['string', 'max:150'], 'General Outpatient Clinic', null, null, 'Eye Clinic'),
            new ImportColumn('department_code', 'Department code', false, ['string'], 'MED', 'Code of an existing department.'),
            new ImportColumn('location', 'Location', false, ['string', 'max:150'], 'Block A, Room 3'),
            new ImportColumn('requires_triage', 'Nurse triage first', false, ['in:0,1'], 'yes', 'yes = patients see a nurse for vitals before the doctor (default yes).', ['yes', 'no'], 'no'),
            new ImportColumn('specialty', 'Clinic type', false, [Rule::in($types)], 'general', null, $types, 'eye'),
            new ImportColumn('description', 'Description', false, ['string', 'max:1000']),
            self::activeColumn(),
        ];
    }

    public function normalise(array $row): array
    {
        $row = parent::normalise($row);
        $row['requires_triage'] = Values::bool($row['requires_triage']);
        $row['specialty'] = Values::option($row['specialty'], config('emr.specialty.clinic_types'), ['physiotherapy' => 'physio', 'optometry' => 'eye', 'ophthalmology' => 'eye']);

        return $row;
    }

    public function check(array &$row): array
    {
        $row['_department_id'] = $this->lookup('department', $row['department_code']);

        return $row['department_code'] && ! $row['_department_id'] ? ["Department \"{$row['department_code']}\" not found — import departments first."] : [];
    }

    protected function fields(): array
    {
        return ['code' => 'code', 'name' => 'name', 'location' => 'location', 'requires_triage' => 'requires_triage',
            'specialty' => 'specialty', 'description' => 'description', 'is_active' => 'is_active'];
    }

    protected function beforeSave(Model $model, array $row, bool $updating): void
    {
        if ($row['_department_id']) {
            $model->department_id = $row['_department_id'];
        }
        $model->requires_triage ??= true;
        $model->specialty ??= 'general';
    }
}
