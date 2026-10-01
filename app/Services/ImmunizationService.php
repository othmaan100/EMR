<?php

namespace App\Services;

use App\Models\Immunization;
use App\Models\Patient;
use App\Models\User;
use App\Models\Vaccine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ImmunizationService
{
    /** A dose more than this many days past its due date is "overdue". */
    public const GRACE_DAYS = 28;

    /**
     * Each scheduled dose with its status for this child:
     * given | due | overdue | upcoming | unknown (no date of birth).
     *
     * @return Collection<int, array{vaccine: Vaccine, due: ?Carbon, status: string, record: ?Immunization}>
     */
    public function schedule(Patient $patient, ?Collection $vaccines = null): Collection
    {
        $vaccines ??= Vaccine::active()->scheduled()->get();
        $given = $patient->relationLoaded('immunizations') ? $patient->immunizations->keyBy('vaccine_id')
            : $patient->immunizations()->get()->keyBy('vaccine_id');
        $dob = $patient->date_of_birth;

        return $vaccines->map(function (Vaccine $v) use ($given, $dob) {
            $due = $dob?->copy()->addDays($v->age_days);
            $record = $given->get($v->id);

            $status = match (true) {
                $record !== null => 'given',
                $due === null => 'unknown',
                $due->gt(today()) => 'upcoming',
                $due->lt(today()->subDays(self::GRACE_DAYS)) => 'overdue',
                default => 'due',
            };

            return ['vaccine' => $v, 'due' => $due, 'status' => $status, 'record' => $record];
        });
    }

    public function record(Patient $patient, Vaccine $vaccine, array $data, User $by): Immunization
    {
        if ($patient->immunizations()->where('vaccine_id', $vaccine->id)->exists()) {
            throw ValidationException::withMessages(['vaccine_id' => "{$vaccine->label} is already recorded for this patient."]);
        }
        if ($patient->date_of_birth && Carbon::parse($data['given_on'])->lt($patient->date_of_birth)) {
            throw ValidationException::withMessages(['given_on' => 'The date given cannot be before the date of birth.']);
        }

        $immunization = new Immunization(['vaccine_id' => $vaccine->id] + $data);
        $immunization->given_by = $by->id;
        $patient->immunizations()->save($immunization);

        return $immunization;
    }

    /**
     * Children under 5 enrolled in immunization (born here or with any dose
     * recorded) who have at least one overdue dose.
     *
     * @return Collection<int, array{patient: Patient, overdue: Collection, next: ?array}>
     */
    public function defaulters(): Collection
    {
        $vaccines = Vaccine::active()->scheduled()->get();

        return Patient::with('immunizations')
            ->where('is_deceased', false)
            ->whereDate('date_of_birth', '>=', today()->subYears(5))
            ->where(fn ($q) => $q->whereNotNull('mother_id')->orWhereHas('immunizations'))
            ->get()
            ->map(function (Patient $p) use ($vaccines) {
                $schedule = $this->schedule($p, $vaccines);

                return ['patient' => $p, 'overdue' => $schedule->where('status', 'overdue')->values()];
            })
            ->filter(fn ($row) => $row['overdue']->isNotEmpty())
            ->sortByDesc(fn ($row) => $row['overdue']->count())
            ->values();
    }
}
