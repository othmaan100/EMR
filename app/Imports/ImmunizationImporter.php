<?php

namespace App\Imports;

use App\Imports\Concerns\Lookups;
use App\Models\Immunization;
use App\Models\Patient;
use App\Models\Vaccine;

class ImmunizationImporter extends Importer
{
    use Lookups;

    public function key(): string
    {
        return 'immunizations';
    }

    public function title(): string
    {
        return 'Immunization history';
    }

    public function group(): string
    {
        return 'Opening balances & history';
    }

    public function description(): string
    {
        return 'Doses children (or adults) already received, from the old register or vaccination cards, so reminders and defaulter lists are right.';
    }

    public function dependsOn(): array
    {
        return ['patients'];
    }

    public function notes(): array
    {
        return ['Vaccine codes are listed under Catalogues → Vaccines (e.g. BCG, OPV0, PENTA1, MR1).'];
    }

    public function matchDescription(): string
    {
        return 'Rows are matched on patient + vaccine; each dose is recorded once.';
    }

    public function columns(): array
    {
        $codes = Vaccine::orderBy('age_days')->orderBy('sort_order')->pluck('code')->all();

        return [
            new ImportColumn('hospital_number', 'Hospital or legacy number', true, ['string', 'max:50'], setting('patient_number_prefix', 'PT').'-000456', null, null, setting('patient_number_prefix', 'PT').'-000456'),
            new ImportColumn('vaccine_code', 'Vaccine code', true, ['string', 'max:20'], $codes[0] ?? 'BCG', null, $codes ?: null, $codes[1] ?? 'OPV0'),
            new ImportColumn('given_on', 'Date given', true, ['date', 'before_or_equal:today'], '2026-03-02', Values::DATE_HELP, null, '2026-03-02'),
            new ImportColumn('batch_number', 'Vaccine batch', false, ['string', 'max:50']),
            new ImportColumn('site', 'Site', false, ['string', 'max:50'], 'Left upper arm'),
            new ImportColumn('notes', 'Notes', false, ['string', 'max:500'], 'From old immunization register'),
        ];
    }

    public function normalise(array $row): array
    {
        $row['hospital_number'] = Values::upper($row['hospital_number']);
        $row['vaccine_code'] = Values::upper($row['vaccine_code']);
        $row['given_on'] = Values::date($row['given_on']);

        return $row;
    }

    public function check(array &$row): array
    {
        $errors = [];
        $row['_patient_id'] = $this->patientId($row['hospital_number']);
        if (! $row['_patient_id']) {
            $errors[] = "No patient with number \"{$row['hospital_number']}\".";
        }
        $row['_vaccine_id'] = $this->lookup('vaccine', $row['vaccine_code']);
        if (! $row['_vaccine_id']) {
            $errors[] = "Vaccine \"{$row['vaccine_code']}\" not found.";
        }
        if ($row['_patient_id'] && ($dob = Patient::whereKey($row['_patient_id'])->value('date_of_birth')) && $row['given_on'] < substr((string) $dob, 0, 10)) {
            $errors[] = 'Date given is before the date of birth.';
        }

        return $errors;
    }

    public function rowKey(array $row): ?string
    {
        return $row['_patient_id'].'|'.$row['_vaccine_id'];
    }

    public function find(array $row): mixed
    {
        return Immunization::where('patient_id', $row['_patient_id'])->where('vaccine_id', $row['_vaccine_id'])->first();
    }

    public function save(array $row, mixed $existing): void
    {
        $dose = $existing ?? new Immunization(['vaccine_id' => $row['_vaccine_id']]);
        $dose->fill(['given_on' => $row['given_on'], 'batch_number' => $row['batch_number'], 'site' => $row['site'], 'notes' => $row['notes'] ?: 'Imported history']);
        $dose->patient_id = $row['_patient_id'];
        $dose->save();
    }
}
