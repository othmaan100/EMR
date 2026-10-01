<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ClaimBatch;
use App\Models\InsuranceProvider;
use App\Models\Preauthorization;
use App\Models\User;
use App\Models\Visit;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HMO / NHIA claims: pre-authorisations → batches → submission → remittance.
 */
class ClaimService
{
    /**
     * Bills with an insurer share that are waiting to be put in a batch.
     */
    public function unbatched(?int $providerId = null): Builder
    {
        return Bill::with(['patient', 'insuranceProvider', 'visit.clinic', 'admission', 'items'])
            ->where('claim_status', 'pending')->whereNull('claim_batch_id')
            ->when($providerId, fn ($q, $id) => $q->where('insurance_provider_id', $id));
    }

    /**
     * Why a bill cannot be claimed yet, or null when it is ready.
     */
    public function blocker(Bill $bill): ?string
    {
        if ($bill->visit && in_array($bill->visit->status, Visit::OPEN_STATUSES, true)) {
            return 'Visit still open';
        }
        if ($bill->admission && $bill->admission->status === 'admitted') {
            return 'Patient still admitted';
        }
        if ($bill->insuranceProvider?->requires_authorization && blank($bill->authorization_code)) {
            return 'Needs PA code';
        }
        if ($bill->totals()['insurance'] <= 0) {
            return 'No insurer share';
        }

        return null;
    }

    /**
     * @param  list<int>  $billIds
     */
    public function createBatch(InsuranceProvider $provider, array $billIds, User $by, ?string $notes = null): ClaimBatch
    {
        return DB::transaction(function () use ($provider, $billIds, $by, $notes) {
            $bills = Bill::with(['visit', 'admission', 'insuranceProvider', 'items'])->whereIn('id', $billIds)->lockForUpdate()->get();

            $errors = [];
            foreach ($bills as $bill) {
                if ($bill->insurance_provider_id !== $provider->id || $bill->claim_status !== 'pending' || $bill->claim_batch_id) {
                    $errors[] = "{$bill->bill_number} is not waiting to be claimed from {$provider->name}.";
                } elseif ($reason = $this->blocker($bill)) {
                    $errors[] = "{$bill->bill_number}: {$reason}.";
                }
            }
            if ($bills->isEmpty() || $errors) {
                throw ValidationException::withMessages(['bills' => $errors ?: ['Select at least one bill.']]);
            }

            $batch = new ClaimBatch([
                'insurance_provider_id' => $provider->id,
                'period_from' => $bills->min('created_at')->toDateString(),
                'period_to' => $bills->max('created_at')->toDateString(),
                'notes' => $notes,
            ]);
            $batch->batch_number = ConsultationService::number('CLM');
            $batch->status = 'draft';
            $batch->created_by = $by->id;
            $batch->save();

            foreach ($bills as $bill) {
                $bill->forceFill(['claim_batch_id' => $batch->id, 'claim_amount' => $bill->totals()['insurance']])->save();
            }
            $this->refreshTotals($batch);

            Audit::log('claim_batch_created', "Claim batch {$batch->batch_number} for {$provider->name}: {$bills->count()} bill(s), ".money($batch->amount_claimed), $batch);

            return $batch;
        });
    }

    public function removeBill(ClaimBatch $batch, Bill $bill): void
    {
        $this->assertStatus($batch, ['draft'], 'change');
        abort_unless($bill->claim_batch_id === $batch->id, 404);

        $bill->forceFill(['claim_batch_id' => null, 'claim_amount' => null])->save();
        $this->refreshTotals($batch);
    }

    public function deleteDraft(ClaimBatch $batch): void
    {
        $this->assertStatus($batch, ['draft'], 'delete');

        DB::transaction(function () use ($batch) {
            $batch->bills()->update(['claim_batch_id' => null, 'claim_amount' => null]);
            Audit::log('claim_batch_deleted', "Draft claim batch {$batch->batch_number} deleted");
            $batch->delete();
        });
    }

    public function submit(ClaimBatch $batch, User $by): void
    {
        $this->assertStatus($batch, ['draft'], 'submit');
        if ($batch->bills()->doesntExist()) {
            throw ValidationException::withMessages(['batch' => 'The batch has no bills.']);
        }

        DB::transaction(function () use ($batch, $by) {
            // Re-freeze amounts in case charges changed while the batch was a draft.
            $batch->bills()->with('items')->get()->each(fn (Bill $b) => $b->forceFill([
                'claim_amount' => $b->totals()['insurance'],
                'claim_status' => 'submitted',
                'claim_submitted_at' => now(),
                'claim_reference' => $batch->batch_number,
            ])->save());

            $batch->forceFill(['status' => 'submitted', 'submitted_at' => now(), 'submitted_by' => $by->id])->save();
            $this->refreshTotals($batch);
        });

        Audit::log('claim_batch_submitted', "Claim batch {$batch->batch_number} submitted: ".money($batch->amount_claimed), $batch);
    }

    /**
     * Record the payer's remittance advice.
     *
     * @param  array<int, array{paid?: ?float, reason?: ?string}>  $lines  bill_id => outcome (blank paid = not yet decided)
     */
    public function remit(ClaimBatch $batch, array $lines, ?string $paidOn, ?string $reference, User $by): void
    {
        $this->assertStatus($batch, ['submitted', 'reconciled'], 'reconcile');

        DB::transaction(function () use ($batch, $lines, $paidOn, $reference) {
            $errors = [];
            foreach ($batch->bills as $bill) {
                $line = $lines[$bill->id] ?? null;
                if ($line === null || ($line['paid'] ?? null) === null || $line['paid'] === '') {
                    continue;
                }
                if ($bill->claim_transferred_at) {
                    continue; // shortfall already billed to the patient: outcome is final
                }

                $paid = round((float) $line['paid'], 2);
                $reason = trim((string) ($line['reason'] ?? '')) ?: null;
                if ($paid < 0 || $paid > $bill->claim_amount) {
                    $errors["lines.{$bill->id}.paid"] = "{$bill->bill_number}: paid must be between 0 and ".money($bill->claim_amount).'.';
                    continue;
                }
                if ($paid < $bill->claim_amount && ! $reason) {
                    $errors["lines.{$bill->id}.reason"] = "{$bill->bill_number}: give the reason for the shortfall.";
                    continue;
                }

                $bill->forceFill([
                    'claim_amount_paid' => $paid,
                    'claim_status' => match (true) {
                        $paid >= $bill->claim_amount => 'paid',
                        $paid > 0 => 'part_paid',
                        default => 'rejected',
                    },
                    'claim_rejection_reason' => $paid < $bill->claim_amount ? $reason : null,
                    'claim_paid_at' => $paid > 0 ? ($paidOn ?? now()) : null,
                ])->save();
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $batch->forceFill(['paid_on' => $paidOn, 'payment_reference' => $reference])->save();
            $this->refreshTotals($batch);
            $undecided = $batch->bills()->where('claim_status', 'submitted')->exists();
            $batch->forceFill(['status' => $undecided ? 'submitted' : 'reconciled'])->save();
        });

        Audit::log('claim_remittance_recorded', "Remittance for {$batch->batch_number}: ".money($batch->amount_paid)." of ".money($batch->amount_claimed)
            .($reference ? " (ref {$reference})" : ''), $batch);
    }

    /**
     * A rejected bill goes back to "to batch" after it has been corrected.
     */
    public function requeue(Bill $bill, User $by): void
    {
        if ($bill->claim_status !== 'rejected' || $bill->claim_transferred_at) {
            throw ValidationException::withMessages(['bill' => 'Only rejected claims that have not been billed to the patient can be resubmitted.']);
        }

        $batch = $bill->claimBatch;
        $bill->forceFill(['claim_status' => 'pending', 'claim_batch_id' => null, 'claim_amount' => null, 'claim_amount_paid' => 0,
            'claim_reference' => null, 'claim_submitted_at' => null])->save();
        if ($batch) {
            $this->refreshTotals($batch);
        }

        Audit::log('claim_requeued', "Claim for {$bill->bill_number} returned for resubmission (was rejected: {$bill->claim_rejection_reason})", $bill);
    }

    /**
     * Move the unpaid insurer share onto the patient's own account.
     */
    public function transferShortfall(Bill $bill, User $by): float
    {
        $shortfall = $bill->claimShortfall();
        if ($shortfall <= 0 || $bill->claim_transferred_at) {
            throw ValidationException::withMessages(['bill' => 'There is no unpaid insurer share to transfer on this bill.']);
        }

        DB::transaction(function () use ($bill, $shortfall) {
            $items = $bill->activeItems()->where('insurance_amount', '>', 0)->lockForUpdate()->get();
            $insured = $items->sum('insurance_amount');
            $left = $shortfall;

            foreach ($items->values() as $i => $item) {
                $move = $i === $items->count() - 1 ? $left : min($left, round($item->insurance_amount * $shortfall / $insured, 2));
                $item->forceFill([
                    'insurance_amount' => round($item->insurance_amount - $move, 2),
                    'patient_amount' => round($item->patient_amount + $move, 2),
                ])->save();
                $left = round($left - $move, 2);
            }

            $bill->forceFill(['claim_transferred_at' => now()])->save();
        });

        Audit::log('claim_shortfall_transferred', money($shortfall)." unpaid by {$bill->insuranceProvider?->name} on {$bill->bill_number} transferred to the patient", $bill);

        return $shortfall;
    }

    public function setAuthorizationCode(Bill $bill, ?string $code, User $by): void
    {
        if (! in_array($bill->claim_status, ['pending', 'rejected'], true)) {
            throw ValidationException::withMessages(['authorization_code' => 'The PA code cannot change after the claim is submitted.']);
        }

        $old = $bill->authorization_code;
        $bill->forceFill(['authorization_code' => $code ?: null])->save();
        Audit::log('claim_pa_code_set', "PA code on {$bill->bill_number}: ".($old ?: '—').' → '.($code ?: '—'), $bill);
    }

    // ------------------------------------------------------------------ pre-authorisation

    public function requestAuthorization(array $data, User $by): Preauthorization
    {
        $pa = new Preauthorization($data);
        $pa->status = 'requested';
        $pa->requested_by = $by->id;
        $pa->save();

        Audit::log('preauth_requested', "Pre-authorisation requested from {$pa->insuranceProvider->name}", $pa->patient);

        return $pa;
    }

    /**
     * @param  array{status: string, code?: ?string, amount_approved?: ?float, valid_until?: ?string, notes?: ?string}  $data
     */
    public function decideAuthorization(Preauthorization $pa, array $data, User $by): void
    {
        if ($pa->status !== 'requested') {
            throw ValidationException::withMessages(['status' => 'This request has already been decided.']);
        }
        if ($data['status'] === 'approved' && blank($data['code'] ?? null)) {
            throw ValidationException::withMessages(['code' => 'Enter the authorisation code given by the HMO.']);
        }

        DB::transaction(function () use ($pa, $data, $by) {
            $pa->forceFill([
                'status' => $data['status'],
                'code' => $data['status'] === 'approved' ? $data['code'] : null,
                'amount_approved' => $data['status'] === 'approved' ? ($data['amount_approved'] ?? null) : null,
                'valid_until' => $data['valid_until'] ?? null,
                'notes' => $data['notes'] ?? $pa->notes,
                'decided_by' => $by->id,
                'decided_at' => now(),
            ])->save();

            // Put the code on the linked bill so the claim can go out.
            if ($pa->status === 'approved' && $pa->bill && in_array($pa->bill->claim_status, ['pending', 'rejected'], true) && blank($pa->bill->authorization_code)) {
                $pa->bill->forceFill(['authorization_code' => $pa->code])->save();
            }
        });

        Audit::log('preauth_'.$pa->status, "Pre-authorisation {$pa->status}".($pa->code ? " (code {$pa->code})" : ''), $pa->patient);
    }

    // ------------------------------------------------------------------ export

    /**
     * One row per claimed service line, the common format HMOs import.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function exportRows(ClaimBatch $batch): Collection
    {
        $batch->loadMissing(['bills.patient', 'bills.visit.consultation.diagnoses', 'bills.admission', 'bills.items', 'insuranceProvider']);

        return $batch->bills->flatMap(function (Bill $bill) use ($batch) {
            $dx = $bill->diagnoses();
            $p = $bill->patient;

            return $bill->items->whereNull('voided_at')->where('insurance_amount', '>', 0)->map(fn (BillItem $item) => [
                'batch' => $batch->batch_number,
                'bill' => $bill->bill_number,
                'enrollee_id' => $p->insurance_number,
                'enrollee_name' => $p->full_name,
                'sex' => $p->gender,
                'date_of_birth' => $p->date_of_birth?->toDateString(),
                'hospital_number' => $p->hospital_number,
                'encounter' => $bill->admission_id ? 'Inpatient' : 'Outpatient',
                'service_date' => $item->created_at->toDateString(),
                'pa_code' => $bill->authorization_code,
                'icd10' => $dx->pluck('icd10_code')->filter()->implode(';'),
                'diagnosis' => $dx->pluck('description')->implode('; ') ?: $bill->admission?->final_diagnosis,
                'service' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'amount' => (float) $item->amount,
                'claimed' => (float) $item->insurance_amount,
            ]);
        })->values();
    }

    protected function refreshTotals(ClaimBatch $batch): void
    {
        $batch->forceFill([
            'amount_claimed' => round((float) $batch->bills()->sum('claim_amount'), 2),
            'amount_paid' => round((float) $batch->bills()->sum('claim_amount_paid'), 2),
        ])->save();
    }

    protected function assertStatus(ClaimBatch $batch, array $allowed, string $action): void
    {
        if (! in_array($batch->status, $allowed, true)) {
            throw ValidationException::withMessages(['batch' => "Cannot {$action} a batch that is {$batch->statusLabel()}."]);
        }
    }
}
