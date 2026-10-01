<?php

namespace App\Imports;

use App\Imports\Concerns\Lookups;
use App\Models\Patient;
use App\Support\Sequence;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PatientImporter extends Importer
{
    use Lookups;

    /** Copied straight onto the patient record. */
    protected const FIELDS = ['legacy_number', 'title', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'marital_status',
        'national_id', 'occupation', 'religion', 'nationality', 'phone', 'alt_phone', 'email', 'address', 'city', 'state', 'country',
        'blood_group', 'genotype', 'allergies', 'nok_name', 'nok_relationship', 'nok_phone', 'nok_address', 'payment_type',
        'insurance_number', 'insurance_expiry', 'notes', 'date_of_death'];

    public function key(): string
    {
        return 'patients';
    }

    public function title(): string
    {
        return 'Patients';
    }

    public function group(): string
    {
        return 'People';
    }

    public function description(): string
    {
        return 'Patient demographics, contacts, next of kin and payment details from the old system (paper registers, spreadsheets or another EMR).';
    }

    public function dependsOn(): array
    {
        return ['insurers'];
    }

    public function notes(): array
    {
        return [
            'Keep the old hospital number: put it in "hospital_number" to keep using it, or in "legacy_number" to issue new numbers and keep the old one searchable.',
            'If neither is given a new hospital number is issued, and the row can never be matched again — so always include one of them.',
            'Where the date of birth is unknown, give "age_years" instead; the birth date is then marked as estimated.',
            'No registration fee is charged for imported patients.',
        ];
    }

    public function matchDescription(): string
    {
        return 'Rows are matched by hospital_number, or by legacy_number when hospital_number is blank.';
    }

    public function columns(): array
    {
        $p = config('emr.patient');
        $prefix = setting('patient_number_prefix', 'PT');

        return [
            new ImportColumn('hospital_number', 'Hospital number', false, ['string', 'max:30'], "{$prefix}-000123", 'Keep this number as the patient\'s hospital number (optional).', null, ''),
            new ImportColumn('legacy_number', 'Old / legacy number', false, ['string', 'max:50'], 'OLD/2015/0456', 'Number from the old system; stays searchable.', null, 'CARD-7781'),
            new ImportColumn('title', 'Title', false, ['string', 'max:20'], 'Mrs', null, null, 'Master'),
            new ImportColumn('first_name', 'First name', true, ['string', 'max:100'], 'Amina', null, null, 'Chinedu'),
            new ImportColumn('middle_name', 'Middle name', false, ['string', 'max:100'], 'Hauwa'),
            new ImportColumn('last_name', 'Surname', true, ['string', 'max:100'], 'Bello', null, null, 'Okafor'),
            new ImportColumn('gender', 'Sex', true, [Rule::in(array_keys($p['genders']))], 'female', 'male, female or other (M / F accepted).', array_keys($p['genders']), 'male'),
            new ImportColumn('date_of_birth', 'Date of birth', false, ['date', 'before_or_equal:today'], '1985-04-12', Values::DATE_HELP, null, ''),
            new ImportColumn('age_years', 'Age (if no date of birth)', false, ['integer', 'between:0,130'], '', 'Age in years when the birth date is unknown.', null, '7'),
            new ImportColumn('marital_status', 'Marital status', false, [Rule::in($p['marital_statuses'])], 'Married', null, $p['marital_statuses'], 'Single'),
            new ImportColumn('phone', 'Phone', false, ['string', 'max:30'], '08031234567', 'Format the column as Text in Excel to keep the leading 0.', null, '08059876543'),
            new ImportColumn('alt_phone', 'Other phone', false, ['string', 'max:30']),
            new ImportColumn('email', 'Email', false, ['email', 'max:255'], 'amina@example.com'),
            new ImportColumn('address', 'Address', false, ['string', 'max:255'], '12 Hospital Road'),
            new ImportColumn('city', 'Town / city', false, ['string', 'max:100'], 'Potiskum'),
            new ImportColumn('state', 'State', false, ['string', 'max:100'], 'Yobe'),
            new ImportColumn('country', 'Country', false, ['string', 'max:100'], 'Nigeria'),
            new ImportColumn('national_id', 'National ID (NIN)', false, ['string', 'max:50']),
            new ImportColumn('occupation', 'Occupation', false, ['string', 'max:100'], 'Teacher', null, null, 'Pupil'),
            new ImportColumn('religion', 'Religion', false, ['string', 'max:50'], 'Islam'),
            new ImportColumn('nationality', 'Nationality', false, ['string', 'max:100'], 'Nigerian'),
            new ImportColumn('blood_group', 'Blood group', false, [Rule::in($p['blood_groups'])], 'O+', null, $p['blood_groups']),
            new ImportColumn('genotype', 'Genotype', false, [Rule::in($p['genotypes'])], 'AA', null, $p['genotypes']),
            new ImportColumn('allergies', 'Allergies', false, ['string', 'max:2000'], 'Penicillin'),
            new ImportColumn('nok_name', 'Next of kin — name', false, ['string', 'max:150'], 'Musa Bello', null, null, 'Ngozi Okafor'),
            new ImportColumn('nok_relationship', 'Next of kin — relationship', false, ['string', 'max:50'], 'Spouse', null, null, 'Parent'),
            new ImportColumn('nok_phone', 'Next of kin — phone', false, ['string', 'max:30'], '08030000001', null, null, '08030000002'),
            new ImportColumn('nok_address', 'Next of kin — address', false, ['string', 'max:255']),
            new ImportColumn('payment_type', 'Payment type', false, [Rule::in(array_keys(Patient::PAYMENT_TYPES))], 'insurance',
                'Default: insurance if insurer_code is given, otherwise self_pay.', array_keys(Patient::PAYMENT_TYPES), 'self_pay'),
            new ImportColumn('insurer_code', 'Insurer / company code', false, ['string', 'max:20'], 'HYG', 'Code of an imported HMO or company.'),
            new ImportColumn('insurance_number', 'Enrollee / member number', false, ['string', 'max:50'], 'HYG/12345/A'),
            new ImportColumn('insurance_expiry', 'Cover expiry date', false, ['date'], '2026-12-31', Values::DATE_HELP),
            new ImportColumn('registered_on', 'Date first registered', false, ['date', 'before_or_equal:today'], '2015-06-01', 'Original registration date (default: today).', null, '2019-02-14'),
            new ImportColumn('date_of_death', 'Date of death', false, ['date', 'before_or_equal:today'], '', 'Only if the patient has died.'),
            new ImportColumn('notes', 'Notes', false, ['string', 'max:2000']),
        ];
    }

    public function normalise(array $row): array
    {
        $p = config('emr.patient');
        $row['hospital_number'] = Values::upper($row['hospital_number']);
        foreach (['phone', 'alt_phone', 'nok_phone'] as $phone) {
            $row[$phone] = Values::phone($row[$phone]);
        }
        $row['gender'] = Values::option($row['gender'], $p['genders'], ['m' => 'male', 'f' => 'female', 'man' => 'male', 'woman' => 'female', 'boy' => 'male', 'girl' => 'female']);
        $row['marital_status'] = Values::option($row['marital_status'], $p['marital_statuses'], ['s' => 'Single', 'm' => 'Married', 'w' => 'Widowed', 'd' => 'Divorced', 'widow' => 'Widowed', 'widower' => 'Widowed']);
        $row['genotype'] = Values::upper($row['genotype']);
        if (is_string($row['blood_group'])) {
            $bg = strtoupper(str_replace(' ', '', $row['blood_group']));
            $row['blood_group'] = str_replace(['POSITIVE', 'POS', 'NEGATIVE', 'NEG', 'RH+', 'RH-'], ['+', '+', '-', '-', '+', '-'], $bg);
        }
        $row['payment_type'] = Values::option($row['payment_type'], Patient::PAYMENT_TYPES, [
            'cash' => 'self_pay', 'self' => 'self_pay', 'self-pay' => 'self_pay', 'private' => 'self_pay',
            'hmo' => 'insurance', 'nhis' => 'insurance', 'nhia' => 'insurance', 'company' => 'corporate', 'retainer' => 'corporate',
            'waiver' => 'free', 'exempt' => 'free',
        ]);
        foreach (['date_of_birth', 'insurance_expiry', 'registered_on', 'date_of_death'] as $date) {
            $row[$date] = Values::date($row[$date]);
        }
        $row['age_years'] = Values::number($row['age_years']);
        if (is_string($row['title'])) {
            $row['title'] = rtrim(ucfirst(strtolower($row['title'])), '.');
        }

        return $row;
    }

    public function check(array &$row): array
    {
        $errors = [];

        $row['_insurer_id'] = $this->lookup('insurer', $row['insurer_code']);
        if ($row['insurer_code'] && ! $row['_insurer_id']) {
            $errors[] = "Insurer \"{$row['insurer_code']}\" not found — import insurers first.";
        }
        $row['payment_type'] ??= $row['_insurer_id'] ? 'insurance' : 'self_pay';
        if (in_array($row['payment_type'], ['insurance', 'corporate'], true) && ! $row['_insurer_id']) {
            $errors[] = 'Payment type "'.$row['payment_type'].'" needs an insurer_code.';
        }

        if ($row['hospital_number'] && Patient::onlyTrashed()->where('hospital_number', $row['hospital_number'])->exists()) {
            $errors[] = "Hospital number {$row['hospital_number']} belongs to an archived patient.";
        }
        if ($row['date_of_birth'] && $row['date_of_death'] && $row['date_of_death'] < $row['date_of_birth']) {
            $errors[] = 'Date of death is before the date of birth.';
        }

        return $errors;
    }

    public function rowKey(array $row): ?string
    {
        return $row['hospital_number'] ? 'h:'.$row['hospital_number'] : ($row['legacy_number'] ? 'l:'.$row['legacy_number'] : null);
    }

    public function find(array $row): mixed
    {
        if ($row['hospital_number']) {
            return Patient::where('hospital_number', $row['hospital_number'])->first();
        }

        return $row['legacy_number'] ? Patient::where('legacy_number', $row['legacy_number'])->first() : null;
    }

    public function save(array $row, mixed $existing): void
    {
        $patient = $existing ?? new Patient;

        foreach (self::FIELDS as $field) {
            if ($existing && $row[$field] === null) {
                continue; // blank cell = keep what the record already has
            }
            $patient->{$field} = $row[$field];
        }

        if ($row['date_of_birth'] === null && $row['age_years'] !== null && ! ($existing?->date_of_birth)) {
            $patient->date_of_birth = today()->subYears((int) $row['age_years'])->startOfYear()->addMonths(6)->toDateString();
            $patient->dob_estimated = true;
        }
        if ($row['_insurer_id']) {
            $patient->insurance_provider_id = $row['_insurer_id'];
        }
        if ($row['date_of_death']) {
            $patient->is_deceased = true;
        }

        if (! $existing) {
            $patient->payment_type ??= 'self_pay';
            if ($row['hospital_number']) {
                $patient->forceFill(['hospital_number' => $row['hospital_number']]);
                $this->protectSequence($row['hospital_number']);
            }
            $patient->forceFill(['registered_by' => $this->user?->id]);
            if ($row['registered_on']) {
                $patient->created_at = Carbon::parse($row['registered_on'])->setTime(9, 0);
            }
        }

        $patient->save();
    }

    /**
     * Imported numbers in our own format must not be handed out again.
     */
    protected function protectSequence(string $number): void
    {
        $prefix = preg_quote((string) setting('patient_number_prefix'), '/');
        if (preg_match("/^{$prefix}-(\d+)$/i", $number, $m)) {
            Sequence::ensureAbove('patient', (int) $m[1]);
        }
    }
}
