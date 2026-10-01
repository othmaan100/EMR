<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InpatientService
{
    public function __construct(protected BillingService $billing) {}

    public function admit(Patient $patient, Bed $bed, array $data, User $by, ?Visit $visit = null): Admission
    {
        if ($patient->is_deceased) {
            throw ValidationException::withMessages(['patient' => 'This patient is recorded as deceased.']);
        }

        return DB::transaction(function () use ($patient, $bed, $data, $by, $visit) {
            Patient::whereKey($patient->id)->lockForUpdate()->first();
            if (Admission::current()->where('patient_id', $patient->id)->exists()) {
                throw ValidationException::withMessages(['patient' => "{$patient->full_name} is already admitted."]);
            }

            $bed = $this->lockAvailable($bed);
            if (! $bed->ward->accepts($patient)) {
                throw ValidationException::withMessages(['bed_id' => "{$bed->ward->name} does not admit {$patient->gender} patients."]);
            }

            $admission = new Admission(['reason' => $data['reason']]);
            $admission->admission_number = ConsultationService::number('ADM');
            $admission->patient_id = $patient->id;
            $admission->visit_id = $visit?->id;
            $admission->ward_id = $bed->ward_id;
            $admission->bed_id = $bed->id;
            $admission->doctor_id = $data['doctor_id'] ?? null;
            $admission->admitted_by = $by->id;
            $admission->admitted_at = now();
            $admission->save();

            $bed->update(['status' => 'occupied']);
            $admission->movements()->create(['to_bed_id' => $bed->id, 'reason' => 'Admitted', 'user_id' => $by->id]);

            // Day 1 bed charge.
            $this->chargeBeds($admission, today(), $by);

            return $admission;
        });
    }

    public function transfer(Admission $admission, Bed $to, string $reason, User $by): void
    {
        $this->assertCurrent($admission);

        DB::transaction(function () use ($admission, $to, $reason, $by) {
            $to = $this->lockAvailable($to);
            if (! $to->ward->accepts($admission->patient)) {
                throw ValidationException::withMessages(['bed_id' => "{$to->ward->name} does not admit {$admission->patient->gender} patients."]);
            }

            // Days so far are billed at the old ward's rate.
            $this->chargeBeds($admission, today(), $by);

            $from = $admission->bed;
            $from?->update(['status' => 'cleaning']);
            $to->update(['status' => 'occupied']);
            $admission->forceFill(['bed_id' => $to->id, 'ward_id' => $to->ward_id])->save();
            $admission->movements()->create(['from_bed_id' => $from?->id, 'to_bed_id' => $to->id, 'reason' => $reason, 'user_id' => $by->id]);
        });
    }

    public function discharge(Admission $admission, array $data, User $by): void
    {
        $this->assertCurrent($admission);

        DB::transaction(function () use ($admission, $data, $by) {
            $this->chargeBeds($admission, today(), $by);

            $admission->fill($data);
            $admission->forceFill(['status' => 'discharged', 'discharged_at' => now(), 'discharged_by' => $by->id])->save();
            $admission->bed?->update(['status' => 'cleaning']);

            if ($data['discharge_type'] === 'died') {
                $admission->patient->forceFill(['is_deceased' => true, 'date_of_death' => today()])->save();
            }
        });

        Audit::log('patient_discharged', "{$admission->admission_number} discharged ({$data['discharge_type']})", $admission);
    }

    /**
     * Post one bed-day charge for each day not yet billed, up to $until.
     * Returns the number of days charged.
     */
    public function chargeBeds(Admission $admission, Carbon $until, ?User $by = null): int
    {
        $service = $admission->ward->bedCharge;
        $from = $admission->bed_charged_until
            ? $admission->bed_charged_until->copy()->addDay()
            : $admission->admitted_at->copy()->startOfDay();

        $days = 0;
        for ($day = $from->copy(); $day->lte($until); $day->addDay()) {
            if ($service) {
                $this->billing->charge($admission->patient, $admission, $service, $admission, 1, $by,
                    "Bed – {$admission->ward->name} (".format_date($day).')');
            }
            $days++;
        }

        if ($days) {
            $admission->forceFill(['bed_charged_until' => $until->toDateString()])->save();
        }

        return $days;
    }

    /**
     * Nightly job: charge every current admission up to today.
     */
    public function chargeAllBeds(): int
    {
        $total = 0;
        Admission::current()->with(['ward.bedCharge', 'patient'])->each(function (Admission $a) use (&$total) {
            $total += DB::transaction(fn () => $this->chargeBeds($a, today()));
        });

        return $total;
    }

    protected function lockAvailable(Bed $bed): Bed
    {
        $bed = Bed::with('ward')->lockForUpdate()->findOrFail($bed->id);
        if ($bed->status !== 'available' || ! $bed->ward->is_active) {
            throw ValidationException::withMessages(['bed_id' => "Bed {$bed->label} in {$bed->ward->name} is not available."]);
        }

        return $bed;
    }

    protected function assertCurrent(Admission $admission): void
    {
        if (! $admission->isCurrent()) {
            throw ValidationException::withMessages(['status' => "{$admission->admission_number} has already been discharged."]);
        }
    }
}
