<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\ClaimBatch;
use App\Models\Clinic;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\Preauthorization;
use App\Models\Price;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\QueueService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimsTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;

    protected User $cashier;

    protected InsuranceProvider $hmo;

    protected Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->seed(CatalogSeeder::class);

        $this->accountant = User::factory()->create()->assignRole('Accountant');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        $this->hmo = InsuranceProvider::factory()->create(['name' => 'Hygeia HMO', 'coverage_percent' => 90, 'requires_authorization' => false]);
        $this->clinic = Clinic::factory()->create(['requires_triage' => false]);

        $consult = Service::where('code', 'CONSULT')->firstOrFail();
        Price::create(['billable_type' => $consult->getMorphClass(), 'billable_id' => $consult->id, 'amount' => 10000]);
    }

    /**
     * An insured, completed outpatient visit: insurer share 9,000.
     */
    protected function insuredBill(string $member = 'HYG-001'): Bill
    {
        $patient = Patient::factory()->create(['payment_type' => 'insurance', 'insurance_provider_id' => $this->hmo->id, 'insurance_number' => $member]);
        $visit = app(QueueService::class)->checkIn($patient, ['clinic_id' => $this->clinic->id], $this->accountant);
        $visit->forceFill(['status' => Visit::COMPLETED])->save();

        return Bill::where('visit_id', $visit->id)->sole();
    }

    public function test_batch_submit_and_full_remittance(): void
    {
        $a = $this->insuredBill('HYG-001');
        $b = $this->insuredBill('HYG-002');
        $this->assertSame('pending', $a->claim_status);

        $this->actingAs($this->accountant)->get(route('billing.claims'))->assertOk()->assertSee($a->bill_number)->assertSee('Hygeia HMO');

        $this->actingAs($this->accountant)->post(route('billing.claims.batches.store'), [
            'insurance_provider_id' => $this->hmo->id, 'bills' => [$a->id, $b->id],
        ])->assertSessionHasNoErrors();

        $batch = ClaimBatch::sole();
        $this->assertSame('draft', $batch->status);
        $this->assertEquals(18000, $batch->amount_claimed);
        $this->assertStringStartsWith('CLM', $batch->batch_number);

        // Draft: remove and re-add is possible; submit freezes it.
        $this->actingAs($this->accountant)->delete(route('billing.claims.remove-bill', [$batch, $b]))->assertSessionHasNoErrors();
        $this->assertEquals(9000, $batch->fresh()->amount_claimed);
        $this->assertNull($b->fresh()->claim_batch_id);

        $this->actingAs($this->accountant)->post(route('billing.claims.submit', $batch))->assertSessionHasNoErrors();
        $a->refresh();
        $this->assertSame('submitted', $a->claim_status);
        $this->assertSame($batch->batch_number, $a->claim_reference);
        $this->actingAs($this->accountant)->delete(route('billing.claims.remove-bill', [$batch, $a]))->assertSessionHasErrors('batch');

        // Print, claim form and CSV.
        $this->actingAs($this->accountant)->get(route('billing.claims.batch', $batch))->assertOk()->assertSee('HYG-001');
        $this->actingAs($this->accountant)->get(route('billing.claims.print', $batch))->assertOk()->assertSee('CLAIM SCHEDULE');
        $this->actingAs($this->accountant)->get(route('billing.claims.form', $a))->assertOk()->assertSee('HEALTH INSURANCE CLAIM FORM')->assertSee('HYG-001');
        $csv = $this->actingAs($this->accountant)->get(route('billing.claims.export', $batch))->assertOk()->streamedContent();
        $this->assertStringContainsString('Enrollee ID', $csv);
        $this->assertStringContainsString('HYG-001', $csv);
        $this->assertStringContainsString('9000', $csv);

        $this->actingAs($this->accountant)->post(route('billing.claims.remit', $batch), [
            'lines' => [$a->id => ['paid' => 9000]], 'paid_on' => today()->toDateString(), 'payment_reference' => 'TRF-55',
        ])->assertSessionHasNoErrors();

        $this->assertSame('paid', $a->fresh()->claim_status);
        $batch->refresh();
        $this->assertSame('reconciled', $batch->status);
        $this->assertEquals(9000, $batch->amount_paid);
        $this->assertSame('TRF-55', $batch->payment_reference);
    }

    public function test_short_payment_needs_reason_and_can_be_billed_to_patient(): void
    {
        $bill = $this->insuredBill();
        $batch = app(\App\Services\ClaimService::class)->createBatch($this->hmo, [$bill->id], $this->accountant);
        app(\App\Services\ClaimService::class)->submit($batch, $this->accountant);

        $this->actingAs($this->accountant)->post(route('billing.claims.remit', $batch), ['lines' => [$bill->id => ['paid' => 6000]]])
            ->assertSessionHasErrors("lines.{$bill->id}.reason");
        $this->actingAs($this->accountant)->post(route('billing.claims.remit', $batch), ['lines' => [$bill->id => ['paid' => 99999, 'reason' => 'x']]])
            ->assertSessionHasErrors("lines.{$bill->id}.paid");

        $this->actingAs($this->accountant)->post(route('billing.claims.remit', $batch), [
            'lines' => [$bill->id => ['paid' => 6000, 'reason' => 'Tariff above agreed price']],
        ])->assertSessionHasNoErrors();
        $bill->refresh();
        $this->assertSame('part_paid', $bill->claim_status);
        $this->assertEquals(3000, $bill->claimShortfall());

        $this->actingAs($this->accountant)->get(route('billing.claims', ['tab' => 'rejected']))->assertOk()->assertSee('Tariff above agreed price');

        $patientBefore = $bill->totals()['patient'];
        $this->actingAs($this->accountant)->post(route('billing.claims.transfer', $bill))->assertSessionHasNoErrors();
        $bill->refresh();
        $t = $bill->totals();
        $this->assertEquals($patientBefore + 3000, $t['patient']);
        $this->assertEquals(6000, $t['insurance']);
        $this->assertEquals(10000, $t['amount']);
        $this->assertNotNull($bill->claim_transferred_at);

        // Cannot transfer twice.
        $this->actingAs($this->accountant)->post(route('billing.claims.transfer', $bill))->assertSessionHasErrors('bill');
    }

    public function test_rejected_claim_can_be_resubmitted_in_a_new_batch(): void
    {
        $bill = $this->insuredBill();
        $claims = app(\App\Services\ClaimService::class);
        $batch = $claims->createBatch($this->hmo, [$bill->id], $this->accountant);
        $claims->submit($batch, $this->accountant);
        $claims->remit($batch, [$bill->id => ['paid' => 0, 'reason' => 'No PA code']], null, null, $this->accountant);
        $this->assertSame('rejected', $bill->fresh()->claim_status);

        $this->actingAs($this->accountant)->post(route('billing.claims.requeue', $bill))->assertSessionHasNoErrors();
        $bill->refresh();
        $this->assertSame('pending', $bill->claim_status);
        $this->assertNull($bill->claim_batch_id);

        $second = $claims->createBatch($this->hmo, [$bill->id], $this->accountant);
        $this->assertNotSame($batch->id, $second->id);
    }

    public function test_open_visits_and_missing_pa_codes_block_batching(): void
    {
        $this->hmo->update(['requires_authorization' => true]);
        $bill = $this->insuredBill();

        $this->actingAs($this->accountant)->get(route('billing.claims'))->assertOk()->assertSee('Needs PA code');
        $this->actingAs($this->accountant)->post(route('billing.claims.batches.store'), ['insurance_provider_id' => $this->hmo->id, 'bills' => [$bill->id]])
            ->assertSessionHasErrors('bills');

        $this->actingAs($this->accountant)->post(route('billing.claims.pa-code', $bill), ['authorization_code' => 'PA/2026/0042'])->assertSessionHasNoErrors();
        $this->actingAs($this->accountant)->post(route('billing.claims.batches.store'), ['insurance_provider_id' => $this->hmo->id, 'bills' => [$bill->id]])
            ->assertSessionHasNoErrors();

        // An open visit cannot be claimed.
        $this->hmo->update(['requires_authorization' => false]);
        $patient = Patient::factory()->create(['payment_type' => 'insurance', 'insurance_provider_id' => $this->hmo->id]);
        app(QueueService::class)->checkIn($patient, ['clinic_id' => $this->clinic->id], $this->accountant);
        $open = Bill::where('patient_id', $patient->id)->sole();
        $this->actingAs($this->accountant)->post(route('billing.claims.batches.store'), ['insurance_provider_id' => $this->hmo->id, 'bills' => [$open->id]])
            ->assertSessionHasErrors('bills');
    }

    public function test_preauthorization_request_and_approval_sets_bill_code(): void
    {
        $bill = $this->insuredBill();
        $patient = $bill->patient;

        $this->actingAs($this->cashier)->get(route('billing.account', $patient))->assertOk()->assertSee('Request PA');
        $this->actingAs($this->cashier)->post(route('preauth.store', $patient), [
            'services' => 'CT scan brain', 'diagnosis' => 'Head injury', 'amount_requested' => 45000, 'bill_id' => $bill->id,
        ])->assertSessionHasNoErrors();

        $pa = Preauthorization::sole();
        $this->assertSame('requested', $pa->status);
        $this->actingAs($this->cashier)->get(route('preauth.index'))->assertOk()->assertSee('CT scan brain');

        $this->actingAs($this->cashier)->post(route('preauth.decide', $pa), ['status' => 'approved'])->assertSessionHasErrors('code');
        $this->actingAs($this->cashier)->post(route('preauth.decide', $pa), ['status' => 'approved', 'code' => 'HYG-PA-777', 'amount_approved' => 40000])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $pa->fresh()->status);
        $this->assertSame('HYG-PA-777', $bill->fresh()->authorization_code);
        $this->actingAs($this->cashier)->post(route('preauth.decide', $pa), ['status' => 'declined'])->assertSessionHasErrors('status');
    }

    public function test_permissions_and_reports(): void
    {
        $this->insuredBill();
        $this->actingAs($this->cashier)->get(route('billing.claims'))->assertForbidden();
        $doctor = User::factory()->create()->assignRole('Doctor');
        $this->actingAs($doctor)->get(route('preauth.index'))->assertForbidden();

        $this->actingAs($this->accountant)->get(route('reports.show', ['report' => 'claims']))->assertOk()->assertSee('Hygeia HMO');
        $this->actingAs($this->accountant)->get(route('reports.show', ['report' => 'claimsAgeing']))->assertOk();
    }
}
