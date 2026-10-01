<?php

namespace App\Integrations\Claims;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ClaimBatch;
use App\Models\IntegrationMessage;
use App\Services\SmsService;
use App\Support\Audit;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Electronic claim file for a batch (structured JSON), and optional
 * submission to an NHIA / HMO claims API configured in Hospital Settings.
 *
 * NOTE: NHIA and HMOs do not share one public API. The payload below uses
 * common claim fields; map it to your payer's specification before go-live.
 */
class ElectronicClaimService
{
    public const FORMAT_VERSION = 'emr-claim-1.0';

    public function configured(): bool
    {
        return filled(setting('claims_api_url')) && filled(SmsService::secret('claims_api_key'));
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ClaimBatch $batch): array
    {
        $batch->loadMissing(['insuranceProvider', 'bills.patient', 'bills.items', 'bills.visit.consultation.diagnoses', 'bills.visit.consultation.doctor', 'bills.admission']);

        return [
            'format' => self::FORMAT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'provider' => [
                'name' => setting('hospital_name'),
                'code' => setting('claims_provider_code') ?: setting('registration_number'),
                'address' => collect([setting('address'), setting('city'), setting('state')])->filter()->implode(', '),
                'phone' => setting('phone'),
            ],
            'payer' => ['name' => $batch->insuranceProvider->name, 'code' => $batch->insuranceProvider->code],
            'batch' => [
                'number' => $batch->batch_number,
                'period_from' => $batch->period_from->toDateString(),
                'period_to' => $batch->period_to->toDateString(),
                'claim_count' => $batch->bills->count(),
                'total_claimed' => round($batch->amount_claimed, 2),
                'currency' => setting('currency_code', 'NGN'),
            ],
            'claims' => $batch->bills->map(fn (Bill $bill) => $this->claim($bill))->values()->all(),
        ];
    }

    public function submit(ClaimBatch $batch): string
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages(['batch' => 'Electronic claim submission is not set up (Hospital Settings → Integrations).']);
        }
        if ($batch->status === 'draft') {
            throw ValidationException::withMessages(['batch' => 'Mark the batch as submitted before sending it electronically.']);
        }

        $payload = $this->payload($batch);
        try {
            $response = Http::timeout(60)->acceptJson()->withToken((string) SmsService::secret('claims_api_key'))
                ->post(setting('claims_api_url'), $payload);
        } catch (Throwable $e) {
            $this->fail($batch, 'Could not reach the claims service: '.$e->getMessage());
        }

        if ($response->failed()) {
            $this->fail($batch, 'The claims service refused the batch (HTTP '.$response->status().'): '.($response->json('message') ?? substr($response->body(), 0, 200)));
        }

        $reference = (string) ($response->json('reference') ?? $response->json('data.reference') ?? $response->json('id') ?? '');
        $batch->forceFill(['submission_status' => 'sent', 'submission_reference' => $reference ?: null, 'submitted_electronically_at' => now()])->save();
        IntegrationMessage::record('claims', 'out', 'ok', "Batch {$batch->batch_number} sent electronically".($reference ? " (ref {$reference})" : ''), $batch->batch_number, null, $batch);
        Audit::log('claims_sent_electronically', "Claim batch {$batch->batch_number} sent to the claims API", $batch);

        return $reference;
    }

    /**
     * @return array<string, mixed>
     */
    protected function claim(Bill $bill): array
    {
        $patient = $bill->patient;
        $diagnoses = $bill->diagnoses();

        return [
            'claim_number' => $bill->bill_number,
            'authorization_code' => $bill->authorization_code,
            'encounter_type' => $bill->admission_id ? 'inpatient' : 'outpatient',
            'encounter_date' => ($bill->admission?->admitted_at ?? $bill->visit?->checked_in_at ?? $bill->created_at)->toDateString(),
            'discharge_date' => $bill->admission?->discharged_at?->toDateString(),
            'enrollee' => [
                'id' => $patient->insurance_number,
                'name' => $patient->full_name,
                'sex' => $patient->gender,
                'date_of_birth' => $patient->date_of_birth?->toDateString(),
                'hospital_number' => $patient->hospital_number,
            ],
            'diagnoses' => $diagnoses->map(fn ($d) => ['icd10' => $d->icd10_code, 'description' => $d->description, 'primary' => (bool) $d->is_primary])->values()->all()
                ?: array_filter([$bill->admission?->final_diagnosis ? ['icd10' => null, 'description' => $bill->admission->final_diagnosis, 'primary' => true] : null]),
            'attending_clinician' => $bill->visit?->consultation?->doctor?->name,
            'services' => $bill->items->whereNull('voided_at')->where('insurance_amount', '>', 0)->map(fn (BillItem $i) => [
                'date' => $i->created_at->toDateString(),
                'description' => $i->description,
                'type' => class_basename((string) $i->billable_type),
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'amount' => (float) $i->amount,
                'claimed' => (float) $i->insurance_amount,
            ])->values()->all(),
            'total_claimed' => round($bill->claim_amount ?? $bill->totals()['insurance'], 2),
        ];
    }

    protected function fail(ClaimBatch $batch, string $message): never
    {
        $batch->forceFill(['submission_status' => 'error'])->save();
        IntegrationMessage::record('claims', 'out', 'error', "Batch {$batch->batch_number}: {$message}", $batch->batch_number, null, $batch);

        throw ValidationException::withMessages(['batch' => $message]);
    }
}
