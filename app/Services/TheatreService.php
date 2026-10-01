<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Surgery;
use App\Models\SurgeryObservation;
use App\Models\SurgicalProcedure;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operating theatre workflow:
 * scheduled → (pre-op) ready → WHO sign in: in_theatre → time out → sign out → operation note: completed.
 */
class TheatreService
{
    public function __construct(protected BillingService $billing) {}

    public function book(Patient $patient, array $data, User $by): Surgery
    {
        if ($patient->is_deceased) {
            throw ValidationException::withMessages(['patient' => 'This patient is recorded as deceased.']);
        }

        return DB::transaction(function () use ($patient, $data, $by) {
            $surgery = new Surgery($this->withProcedureName($data));
            $this->assertNoClash($surgery);

            $surgery->surgery_number = ConsultationService::number('SUR');
            $surgery->patient_id = $patient->id;
            $surgery->admission_id ??= Admission::current()->where('patient_id', $patient->id)->value('id');
            $surgery->booked_by = $by->id;
            $surgery->save();

            return $surgery;
        });
    }

    public function reschedule(Surgery $surgery, array $data): void
    {
        $this->assertStatus($surgery, ['scheduled', 'ready', 'postponed'], 'change the booking of');

        DB::transaction(function () use ($surgery, $data) {
            $surgery->fill($this->withProcedureName($data));
            $this->assertNoClash($surgery);
            if ($surgery->status === 'postponed') {
                $surgery->status = $surgery->assessed_at ? 'ready' : 'scheduled';
                $surgery->cancel_reason = null;
            }
            $surgery->save();
        });
    }

    public function stop(Surgery $surgery, string $status, string $reason): void
    {
        $this->assertStatus($surgery, ['scheduled', 'ready', 'postponed'], $status === 'cancelled' ? 'cancel' : 'postpone');
        $surgery->forceFill(['status' => $status, 'cancel_reason' => $reason])->save();
        Audit::log("surgery_{$status}", "{$surgery->surgery_number} {$status}: {$reason}", $surgery);
    }

    public function preop(Surgery $surgery, array $data, User $by): void
    {
        $this->assertStatus($surgery, ['scheduled', 'ready'], 'record the pre-op assessment of');

        $surgery->fill($data);
        $surgery->assessed_by = $by->id;
        $surgery->assessed_at = now();
        // Ready for theatre once consent, fasting and ASA grade are documented.
        $surgery->status = $surgery->consent_signed && $surgery->fasting_confirmed && $surgery->asa_grade ? 'ready' : 'scheduled';
        $surgery->save();
    }

    /**
     * Complete one WHO checklist phase. Phases must be done in order and
     * every item confirmed.
     *
     * @param  list<int>  $confirmed  indexes of ticked items
     */
    public function checklist(Surgery $surgery, string $phase, array $confirmed, User $by): void
    {
        $definition = Surgery::CHECKLIST[$phase] ?? abort(404);

        if ($surgery->nextPhase() !== $phase) {
            throw ValidationException::withMessages(['checklist' => $surgery->nextPhase()
                ? 'Complete "'.Surgery::CHECKLIST[$surgery->nextPhase()]['title'].'" first.'
                : 'The checklist is already complete.']);
        }
        if ($phase === 'sign_in' && $surgery->status !== 'ready') {
            throw ValidationException::withMessages(['checklist' => 'Complete the pre-operative assessment (consent, fasting, ASA) before sign in.']);
        }
        if ($phase !== 'sign_in') {
            $this->assertStatus($surgery, ['in_theatre'], 'continue the checklist of');
        }

        $missing = array_diff(array_keys($definition['items']), array_map('intval', $confirmed));
        if ($missing) {
            throw ValidationException::withMessages(['checklist' => 'Every item must be confirmed before "'.$definition['title'].'" ('.count($missing).' outstanding).']);
        }

        $checklist = $surgery->checklist ?? [];
        $checklist[$phase] = ['items' => $definition['items'], 'by' => $by->id, 'by_name' => $by->name, 'at' => now()->toIso8601String()];
        $surgery->checklist = $checklist;

        match ($phase) {
            'sign_in' => $surgery->forceFill(['status' => 'in_theatre', 'in_theatre_at' => now()]),
            'time_out' => $surgery->forceFill(['incision_at' => now()]),
            'sign_out' => $surgery->forceFill(['out_at' => now()]),
        };
        $surgery->save();

        Audit::log('surgery_checklist', "{$surgery->surgery_number}: WHO {$definition['title']} completed", $surgery);
    }

    public function observe(Surgery $surgery, array $data, User $by): SurgeryObservation
    {
        $this->assertStatus($surgery, ['in_theatre'], 'record observations for');

        $obs = new SurgeryObservation($data + ['recorded_at' => now()]);
        $obs->recorded_by = $by->id;
        $surgery->observations()->save($obs);

        return $obs;
    }

    public function anaesthesia(Surgery $surgery, array $data): void
    {
        $this->assertStatus($surgery, ['ready', 'in_theatre'], 'update the anaesthesia record of');
        $surgery->fill($data)->save();
    }

    /**
     * The surgeon's operation note closes the case and raises the charges.
     */
    public function complete(Surgery $surgery, array $data, User $by): void
    {
        $this->assertStatus($surgery, ['in_theatre'], 'complete');
        if (! $surgery->phaseDone('sign_out')) {
            throw ValidationException::withMessages(['checklist' => 'Complete the WHO "Sign out" before signing the operation note.']);
        }

        DB::transaction(function () use ($surgery, $data, $by) {
            $surgery->fill($data);
            $surgery->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

            $patient = $surgery->patient;
            $context = $surgery->admission ?? $patient->visits()->open()->first();
            if ($surgery->procedure) {
                $this->billing->charge($patient, $context, $surgery->procedure, $surgery, 1, $by);
            }
            if ($fee = Service::active()->where('code', Service::THEATRE_FEE)->first()) {
                $this->billing->charge($patient, $context, $fee, $surgery, 1, $by);
            }
        });

        Audit::log('surgery_completed', "{$surgery->surgery_number} completed: {$surgery->procedure_name}", $surgery);
    }

    protected function withProcedureName(array $data): array
    {
        if (! empty($data['surgical_procedure_id']) && empty($data['procedure_name'])) {
            $data['procedure_name'] = SurgicalProcedure::find($data['surgical_procedure_id'])?->name;
        }

        return $data;
    }

    /**
     * No two active operations may overlap in the same theatre or for the same surgeon.
     */
    protected function assertNoClash(Surgery $surgery): void
    {
        $start = Carbon::parse($surgery->scheduled_at);
        $end = $start->copy()->addMinutes((int) $surgery->estimated_minutes);

        $others = Surgery::active()->whereKeyNot($surgery->id)
            ->whereBetween('scheduled_at', [$start->copy()->subDay(), $end])
            ->where(fn ($q) => $q->where('theatre_id', $surgery->theatre_id)
                ->when($surgery->surgeon_id, fn ($q) => $q->orWhere('surgeon_id', $surgery->surgeon_id)))
            ->get();

        foreach ($others as $other) {
            if ($start->lt($other->endsAt()) && $other->scheduled_at->lt($end)) {
                $who = $other->theatre_id === (int) $surgery->theatre_id ? 'This theatre' : 'The surgeon';
                throw ValidationException::withMessages([
                    'scheduled_at' => "{$who} is already booked {$other->scheduled_at->format('H:i')}–{$other->endsAt()->format('H:i')} ({$other->surgery_number}).",
                ]);
            }
        }
    }

    protected function assertStatus(Surgery $surgery, array $allowed, string $action): void
    {
        if (! in_array($surgery->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Cannot {$action} {$surgery->surgery_number} while it is \"{$surgery->statusLabel()}\"."]);
        }
    }
}
