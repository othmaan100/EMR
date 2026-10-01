<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Support\Audit;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Check-in and movement of patients through a clinic queue.
 */
class QueueService
{
    public function __construct(protected BillingService $billing) {}

    /**
     * @param  array{clinic_id: int, doctor_id?: ?int, visit_type?: string, priority?: string, complaint?: ?string}  $data
     */
    public function checkIn(Patient $patient, array $data, User $by, ?Appointment $appointment = null): Visit
    {
        if ($patient->is_deceased) {
            throw ValidationException::withMessages(['patient' => 'This patient is recorded as deceased.']);
        }

        return DB::transaction(function () use ($patient, $data, $by, $appointment) {
            // Lock the patient row so double-clicks can't create two visits.
            Patient::whereKey($patient->id)->lockForUpdate()->first();

            $open = Visit::where('patient_id', $patient->id)->open()->first();
            if ($open) {
                throw ValidationException::withMessages([
                    'patient' => "{$patient->full_name} already has an open visit ({$open->visit_number}, {$open->statusLabel()}).",
                ]);
            }

            $clinic = Clinic::findOrFail($data['clinic_id']);
            $now = now();

            $visit = new Visit([
                'patient_id' => $patient->id,
                'clinic_id' => $clinic->id,
                'doctor_id' => $data['doctor_id'] ?? null,
                'visit_type' => $data['visit_type'] ?? 'outpatient',
                'priority' => $data['priority'] ?? 'normal',
                'complaint' => $data['complaint'] ?? null,
                'status' => $clinic->requires_triage ? Visit::WAITING_TRIAGE : Visit::WAITING_DOCTOR,
            ]);
            $visit->visit_number = 'V'.$now->format('Y').'-'.str_pad((string) Sequence::next('visit:'.$now->format('Y')), 6, '0', STR_PAD_LEFT);
            $visit->queue_number = $clinic->code.'-'.str_pad((string) Sequence::next("queue:{$clinic->id}:".$now->format('Ymd')), 3, '0', STR_PAD_LEFT);
            $visit->checked_in_at = $now;
            $visit->checked_in_by = $by->id;
            if (! $clinic->requires_triage) {
                $visit->triaged_at = $now;
            }
            $visit->save();
            $this->billing->chargeVisit($visit, $by);

            if ($appointment) {
                $appointment->forceFill(['status' => 'checked_in', 'visit_id' => $visit->id])->save();
            }

            return $visit;
        });
    }

    public function move(Visit $visit, string $to, User $by, ?string $note = null): Visit
    {
        if (! $visit->canMoveTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move a visit from \"{$visit->statusLabel()}\" to \"".(Visit::STATUSES[$to]['label'] ?? $to).'".',
            ]);
        }

        $from = $visit->status;
        $now = now();

        $visit->status = $to;
        match ($to) {
            Visit::WAITING_DOCTOR => $visit->triaged_at ??= $now,
            Visit::IN_CONSULTATION => $this->startConsultation($visit, $by, $now),
            Visit::COMPLETED, Visit::LEFT, Visit::CANCELLED => $visit->completed_at = $now,
            default => null,
        };
        if ($note) {
            $visit->closing_note = $note;
        }
        $visit->save();

        if (in_array($to, [Visit::COMPLETED, Visit::LEFT, Visit::CANCELLED], true)) {
            $visit->appointment?->forceFill(['status' => match ($to) {
                Visit::COMPLETED => 'completed',
                Visit::LEFT => 'no_show',
                default => 'cancelled',
            }])->save();
        }

        Audit::log('visit_moved', "{$visit->visit_number}: {$from} → {$to}", $visit);

        return $visit;
    }

    protected function startConsultation(Visit $visit, User $by, $now): void
    {
        $visit->consultation_started_at = $now;
        // The first doctor to call an unassigned patient takes them.
        if (! $visit->doctor_id && $by->hasRole('Doctor')) {
            $visit->doctor_id = $by->id;
        }
    }
}
