<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\User;
use App\Models\Visit;
use App\Support\Audit;
use App\Support\Sequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsultationService
{
    public function __construct(protected QueueService $queue) {}

    /**
     * Get the visit's consultation, creating a draft for the doctor if the
     * patient is in consultation and none exists yet.
     */
    public function forVisit(Visit $visit, User $user): ?Consultation
    {
        if ($visit->consultation) {
            return $visit->consultation;
        }

        if ($visit->status !== Visit::IN_CONSULTATION || ! $user->can('consultations.create')) {
            return null;
        }

        $consultation = new Consultation(['presenting_complaint' => $visit->complaint]);
        $consultation->visit_id = $visit->id;
        $consultation->patient_id = $visit->patient_id;
        $consultation->doctor_id = $user->id;
        $consultation->save();

        if (! $visit->doctor_id) {
            $visit->update(['doctor_id' => $user->id]);
        }

        return $visit->setRelation('consultation', $consultation)->consultation;
    }

    /**
     * Sign the consultation (locking it) and complete the visit.
     *
     * @param  array{date?: ?string, time?: ?string, clinic_id?: ?int, reason?: ?string}|null  $followUp
     */
    public function sign(Consultation $consultation, User $doctor, ?array $followUp = null): ?Appointment
    {
        $errors = [];
        if (blank($consultation->presenting_complaint)) {
            $errors['presenting_complaint'] = 'Record the presenting complaint before signing.';
        }
        if ($consultation->diagnoses()->doesntExist()) {
            $errors['diagnosis'] = 'Add at least one diagnosis before signing.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($consultation, $doctor, $followUp) {
            if ($consultation->diagnoses()->where('is_primary', true)->doesntExist()) {
                $consultation->diagnoses()->first()->update(['is_primary' => true]);
            }

            $consultation->forceFill(['status' => 'signed', 'signed_at' => now()])->save();
            Audit::log('consultation_signed', "Consultation for visit {$consultation->visit->visit_number} signed", $consultation);

            $visit = $consultation->visit;
            if ($visit->status === Visit::IN_CONSULTATION) {
                $this->queue->move($visit, Visit::COMPLETED, $doctor);
            }

            if (empty($followUp['date'])) {
                return null;
            }

            $appointment = new Appointment([
                'patient_id' => $consultation->patient_id,
                'clinic_id' => $followUp['clinic_id'] ?? $visit->clinic_id,
                'doctor_id' => $doctor->id,
                'scheduled_at' => Carbon::parse($followUp['date'].' '.($followUp['time'] ?? '09:00')),
                'type' => 'follow_up',
                'reason' => $followUp['reason'] ?? 'Follow-up: '.$consultation->diagnoses()->where('is_primary', true)->value('description'),
            ]);
            $appointment->booked_by = $doctor->id;
            $appointment->save();

            return $appointment;
        });
    }

    public static function number(string $prefix): string
    {
        $year = now()->format('Y');

        return $prefix.$year.'-'.str_pad((string) Sequence::next(strtolower($prefix).':'.$year), 6, '0', STR_PAD_LEFT);
    }
}
