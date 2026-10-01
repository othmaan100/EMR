<?php

namespace App\Http\Requests;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware handles permissions.
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => Str::title(trim((string) $this->input('first_name'))),
            'middle_name' => $this->filled('middle_name') ? Str::title(trim($this->input('middle_name'))) : null,
            'last_name' => Str::title(trim((string) $this->input('last_name'))),
        ]);
    }

    public function rules(): array
    {
        $lists = config('emr.patient');
        $patient = $this->route('patient');
        $needsProvider = ['insurance', 'corporate'];

        return [
            'legacy_number' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', Rule::in($lists['titles'])],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::in(array_keys($lists['genders']))],
            'date_of_birth' => ['nullable', 'required_without:age_years', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'age_years' => ['nullable', 'integer', 'min:0', 'max:130'],
            'marital_status' => ['nullable', Rule::in($lists['marital_statuses'])],
            'national_id' => ['nullable', 'string', 'max:50',
                Rule::unique('patients')->ignore($patient)->whereNull('deleted_at')],
            'occupation' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],

            'phone' => ['nullable', 'string', 'max:30'],
            'alt_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],

            'blood_group' => ['nullable', Rule::in($lists['blood_groups'])],
            'genotype' => ['nullable', Rule::in($lists['genotypes'])],
            'allergies' => ['nullable', 'string', 'max:1000'],

            'nok_name' => ['nullable', 'string', 'max:150'],
            'nok_relationship' => ['nullable', Rule::in($lists['relationships'])],
            'nok_phone' => ['nullable', 'string', 'max:30'],
            'nok_address' => ['nullable', 'string', 'max:255'],

            'payment_type' => ['required', Rule::in(array_keys(Patient::PAYMENT_TYPES))],
            'insurance_provider_id' => ['nullable', Rule::requiredIf(in_array($this->input('payment_type'), $needsProvider, true)),
                Rule::exists('insurance_providers', 'id')],
            'insurance_number' => ['nullable', 'required_if:payment_type,insurance', 'string', 'max:50'],
            'insurance_expiry' => ['nullable', 'date'],

            'notes' => ['nullable', 'string', 'max:2000'],
            'is_deceased' => ['boolean'],
            'date_of_death' => ['nullable', 'required_if_accepted:is_deceased', 'date', 'before_or_equal:today'],

            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'photo_data' => ['nullable', 'string', 'regex:/^data:image\/(jpeg|png);base64,/', 'max:6000000'],
            'remove_photo' => ['boolean'],
            'confirm_duplicate' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nok_name' => 'next of kin name',
            'nok_relationship' => 'relationship',
            'nok_phone' => 'next of kin phone',
            'insurance_provider_id' => 'insurance / company',
            'age_years' => 'age',
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.required_without' => 'Enter a date of birth, or an estimated age if unknown.',
        ];
    }

    /**
     * Validated patient attributes, with estimated DOB derived from age.
     */
    public function patientData(): array
    {
        $data = collect($this->validated())
            ->except(['age_years', 'photo', 'photo_data', 'remove_photo', 'confirm_duplicate'])
            ->all();

        if (empty($data['date_of_birth']) && $this->filled('age_years')) {
            $data['date_of_birth'] = now()->subYears((int) $this->input('age_years'))->toDateString();
            $data['dob_estimated'] = true;
        } elseif (! empty($data['date_of_birth'])) {
            $data['dob_estimated'] = false;
        }

        if (! in_array($data['payment_type'], ['insurance', 'corporate'], true)) {
            $data['insurance_provider_id'] = null;
            $data['insurance_number'] = null;
            $data['insurance_expiry'] = null;
        }

        $data['is_deceased'] = $this->boolean('is_deceased');
        if (! $data['is_deceased']) {
            $data['date_of_death'] = null;
        }

        return $data;
    }
}
