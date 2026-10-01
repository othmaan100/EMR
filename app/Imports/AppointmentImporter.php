<?php

namespace App\Imports;

use App\Imports\Concerns\Lookups;
use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AppointmentImporter extends Importer
{
    use Lookups;

    public function key(): string
    {
        return 'appointments';
    }

    public function title(): string
    {
        return 'Upcoming appointments';
    }

    public function group(): string
    {
        return 'Opening balances & history';
    }

    public function description(): string
    {
        return 'Future appointments already booked in the old system or the appointment book.';
    }

    public function dependsOn(): array
    {
        return ['patients', 'clinics', 'staff'];
    }

    public function notes(): array
    {
        return ['Only appointments from today onwards are imported. Appointment reminders (if SMS is on) are sent for them as usual.'];
    }

    public function matchDescription(): string
    {
        return 'An appointment for the same patient, clinic, date and time is skipped as already imported.';
    }

    public function supportsUpdate(): bool
    {
        return false;
    }

    public function columns(): array
    {
        return [
            new ImportColumn('hospital_number', 'Hospital or legacy number', true, ['string', 'max:50'], setting('patient_number_prefix', 'PT').'-000123'),
            new ImportColumn('clinic_code', 'Clinic code', true, ['string', 'max:10'], 'GOPD'),
            new ImportColumn('date', 'Date', true, ['date', 'after_or_equal:today'], today()->addWeek()->toDateString(), Values::DATE_HELP),
            new ImportColumn('time', 'Time', true, ['date_format:H:i'], '09:30', 'e.g. 09:30 or 2:15 PM.'),
            new ImportColumn('doctor_username', 'Doctor (username)', false, ['string', 'max:50'], 'amusa'),
            new ImportColumn('type', 'Type', false, [Rule::in(array_keys(Appointment::TYPES))], 'follow_up', null, array_keys(Appointment::TYPES)),
            new ImportColumn('reason', 'Reason', false, ['string', 'max:255'], 'Blood pressure review'),
        ];
    }

    public function normalise(array $row): array
    {
        $row['hospital_number'] = Values::upper($row['hospital_number']);
        $row['clinic_code'] = Values::upper($row['clinic_code']);
        $row['date'] = Values::date($row['date']);
        $row['time'] = Values::time($row['time']);
        $row['type'] = Values::option($row['type'], Appointment::TYPES, ['followup' => 'follow_up', 'follow up' => 'follow_up', 'review' => 'review']);

        return $row;
    }

    public function check(array &$row): array
    {
        $errors = [];
        $row['_patient_id'] = $this->patientId($row['hospital_number']);
        if (! $row['_patient_id']) {
            $errors[] = "No patient with number \"{$row['hospital_number']}\".";
        }
        $row['_clinic_id'] = $this->lookup('clinic', $row['clinic_code']);
        if (! $row['_clinic_id']) {
            $errors[] = "Clinic \"{$row['clinic_code']}\" not found.";
        }
        $row['_doctor_id'] = $this->lookup('user', $row['doctor_username']);
        if ($row['doctor_username'] && ! $row['_doctor_id']) {
            $errors[] = "No staff account \"{$row['doctor_username']}\".";
        }
        $row['_at'] = Carbon::parse($row['date'].' '.$row['time'])->toDateTimeString();

        return $errors;
    }

    public function rowKey(array $row): ?string
    {
        return "{$row['_patient_id']}|{$row['_clinic_id']}|{$row['_at']}";
    }

    public function find(array $row): mixed
    {
        return Appointment::where('patient_id', $row['_patient_id'])->where('clinic_id', $row['_clinic_id'])->where('scheduled_at', $row['_at'])->first();
    }

    public function save(array $row, mixed $existing): void
    {
        $appointment = new Appointment([
            'patient_id' => $row['_patient_id'],
            'clinic_id' => $row['_clinic_id'],
            'doctor_id' => $row['_doctor_id'],
            'scheduled_at' => $row['_at'],
            'type' => $row['type'] ?? 'follow_up',
            'reason' => $row['reason'],
            'notes' => 'Imported from previous system',
        ]);
        $appointment->status = 'scheduled';
        $appointment->booked_by = $this->user?->id;
        $appointment->save();
    }
}
