<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\DentalFinding;
use App\Models\EyeExam;
use App\Models\Patient;
use App\Models\PhysioEpisode;
use App\Models\PhysioSession;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dental, eye and physiotherapy records, with their charges.
 * Charges go on the patient's open visit (or current admission) bill.
 */
class SpecialtyService
{
    public function __construct(protected BillingService $billing) {}

    public function context(Patient $patient): Visit|Admission|null
    {
        return $patient->admissions()->current()->latest('id')->first()
            ?? $patient->visits()->open()->latest('id')->first();
    }

    // ------------------------------------------------------------------ dental

    public function recordDental(Patient $patient, array $data, User $by): DentalFinding
    {
        if (($data['status'] ?? 'existing') !== 'existing' && empty($data['service_id'])) {
            throw ValidationException::withMessages(['service_id' => 'Choose the treatment for a planned or completed item.']);
        }
        if (($data['status'] ?? 'existing') === 'existing' && empty($data['condition'])) {
            throw ValidationException::withMessages(['condition' => 'Choose what you found on the tooth.']);
        }

        return DB::transaction(function () use ($patient, $data, $by) {
            $finding = new DentalFinding($data);
            $finding->patient_id = $patient->id;
            $finding->visit_id = $this->context($patient) instanceof Visit ? $this->context($patient)->id : null;
            $finding->recorded_by = $by->id;
            $finding->save();

            if ($finding->status === 'completed') {
                $this->completeDental($finding, $by, alreadySaved: true);
            }

            return $finding;
        });
    }

    public function completeDental(DentalFinding $finding, User $by, bool $alreadySaved = false): void
    {
        if (! $alreadySaved && $finding->status !== 'planned') {
            throw ValidationException::withMessages(['status' => 'Only planned treatment can be marked done.']);
        }

        DB::transaction(function () use ($finding, $by) {
            $finding->forceFill(['status' => 'completed', 'completed_by' => $by->id, 'completed_at' => now()])->save();

            if ($finding->service) {
                $this->billing->charge($finding->patient, $this->context($finding->patient), $finding->service, $finding, 1, $by,
                    $finding->service->name.($finding->tooth ? " (tooth {$finding->tooth})" : ''));
            }
        });
    }

    public function cancelDental(DentalFinding $finding, User $by): void
    {
        if ($finding->status !== 'planned') {
            throw ValidationException::withMessages(['status' => 'Only planned treatment can be cancelled.']);
        }
        $finding->forceFill(['status' => 'cancelled'])->save();
    }

    /**
     * Latest known condition per tooth, for the chart.
     *
     * @return array<int, array{condition: ?string, planned: bool}>
     */
    public function toothMap(Patient $patient): array
    {
        $map = [];
        $findings = DentalFinding::where('patient_id', $patient->id)->whereNotNull('tooth')->where('status', '!=', 'cancelled')->orderBy('id')->get();

        foreach ($findings as $f) {
            $map[$f->tooth] ??= ['condition' => null, 'planned' => false];
            if ($f->condition && $f->status !== 'planned') {
                $map[$f->tooth]['condition'] = $f->condition;
            }
        }
        foreach ($findings->where('status', 'planned') as $f) {
            $map[$f->tooth]['planned'] = true;
        }

        return $map;
    }

    // ------------------------------------------------------------------ eye

    public function recordEye(Patient $patient, array $data, User $by, bool $chargeRefraction): EyeExam
    {
        return DB::transaction(function () use ($patient, $data, $by, $chargeRefraction) {
            $exam = new EyeExam($data);
            $exam->patient_id = $patient->id;
            $context = $this->context($patient);
            $exam->visit_id = $context instanceof Visit ? $context->id : null;
            $exam->examined_by = $by->id;
            $exam->save();

            if ($chargeRefraction && $service = Service::active()->where('code', Service::EYE_REFRACTION)->first()) {
                $this->billing->charge($patient, $context, $service, $exam, 1, $by);
            }

            if ($exam->alerts()) {
                Audit::log('eye_exam_alert', 'Eye exam alert: '.implode('; ', $exam->alerts()), $patient);
            }

            return $exam;
        });
    }

    // ------------------------------------------------------------------ physiotherapy

    public function startPhysio(Patient $patient, array $data, User $by): PhysioEpisode
    {
        return DB::transaction(function () use ($patient, $data, $by) {
            $episode = new PhysioEpisode($data);
            $episode->patient_id = $patient->id;
            $context = $this->context($patient);
            $episode->visit_id = $context instanceof Visit ? $context->id : null;
            $episode->status = 'active';
            $episode->created_by = $by->id;
            $episode->save();

            if ($service = Service::active()->where('code', Service::PHYSIO_ASSESSMENT)->first()) {
                $this->billing->charge($patient, $context, $service, $episode, 1, $by);
            }

            return $episode;
        });
    }

    public function addSession(PhysioEpisode $episode, array $data, User $by): PhysioSession
    {
        if (! $episode->isActive()) {
            throw ValidationException::withMessages(['status' => 'This course of physiotherapy has been discharged.']);
        }

        return DB::transaction(function () use ($episode, $data, $by) {
            $session = new PhysioSession($data);
            $context = $this->context($episode->patient);
            $session->visit_id = $context instanceof Visit ? $context->id : null;
            $session->therapist_id = $by->id;
            $episode->sessions()->save($session);

            if ($service = Service::active()->where('code', Service::PHYSIO_SESSION)->first()) {
                $this->billing->charge($episode->patient, $context, $service, $session, 1, $by,
                    'Physiotherapy session — '.$session->session_date->format('d M Y'));
            }

            return $session;
        });
    }

    public function discharge(PhysioEpisode $episode, array $data, User $by): void
    {
        if (! $episode->isActive()) {
            throw ValidationException::withMessages(['status' => 'Already discharged.']);
        }

        $episode->forceFill(['status' => 'discharged', 'outcome' => $data['outcome'], 'discharge_notes' => $data['discharge_notes'] ?? null,
            'discharged_at' => now()])->save();
        Audit::log('physio_discharged', "Physiotherapy ({$episode->region}) discharged: {$episode->outcomeLabel()}", $episode->patient);
    }
}
