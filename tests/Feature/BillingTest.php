<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\InsuranceProvider;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Price;
use App\Models\Service;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Visit;
use App\Services\QueueService;
use App\Support\Settings;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected User $cashier;

    protected User $accountant;

    protected Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->seed(CatalogSeeder::class);

        $this->doctor = User::factory()->create()->assignRole('Doctor');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        $this->accountant = User::factory()->create()->assignRole('Accountant');
        $this->clinic = Clinic::factory()->create(['requires_triage' => false]);

        $this->price(Service::where('code', 'REG')->first(), 1000);
        $this->price(Service::where('code', 'CONSULT')->first(), 3000);
        $this->price(LabTest::where('code', 'FBC')->first(), 2500);
        $this->price(Drug::where('name', 'Paracetamol')->where('form', 'Tablet')->first(), 10);
    }

    protected function price($billable, float $amount, ?int $providerId = null): void
    {
        Price::create(['billable_type' => $billable->getMorphClass(), 'billable_id' => $billable->id, 'insurance_provider_id' => $providerId, 'amount' => $amount]);
    }

    protected function visitFor(Patient $patient): Visit
    {
        return app(QueueService::class)->checkIn($patient, ['clinic_id' => $this->clinic->id], $this->doctor);
    }

    protected function consult(Visit $visit): Consultation
    {
        $this->actingAs($this->doctor)->patch(route('visits.move', $visit), ['status' => Visit::IN_CONSULTATION]);
        $this->actingAs($this->doctor)->get(route('consultations.show', $visit));

        return Consultation::where('visit_id', $visit->id)->firstOrFail();
    }

    public function test_registration_and_check_in_are_charged(): void
    {
        $records = User::factory()->create()->assignRole('Records Officer');
        $this->actingAs($records)->post(route('patients.store'), [
            'first_name' => 'Ada', 'last_name' => 'Obi', 'gender' => 'female', 'date_of_birth' => '1990-01-01', 'payment_type' => 'self_pay',
        ]);
        $patient = Patient::firstOrFail();
        $this->visitFor($patient);

        $items = BillItem::orderBy('id')->get();
        $this->assertSame(['Registration / card fee', 'Consultation fee'], $items->pluck('description')->all());
        $this->assertSame(4000.0, $patient->outstandingBalance());
        $this->assertSame(2, Bill::count()); // registration bill + visit bill
    }

    public function test_clinic_specific_consultation_fee_wins(): void
    {
        $fee = Service::create(['code' => 'CON-PAED', 'name' => 'Paediatric consultation', 'category' => 'Consultation', 'clinic_id' => $this->clinic->id]);
        $this->price($fee, 5000);

        $this->visitFor(Patient::factory()->create());

        $this->assertSame('Paediatric consultation', BillItem::sole()->description);
    }

    public function test_unpriced_items_are_not_charged(): void
    {
        $patient = Patient::factory()->create();
        $consultation = $this->consult($this->visitFor($patient));

        $this->actingAs($this->doctor)->post(route('consultations.lab.store', $consultation), [
            'tests' => LabTest::whereIn('code', ['FBC', 'ESR'])->pluck('id')->all(), 'priority' => 'routine', // ESR has no price
        ]);

        $this->assertSame(['Consultation fee', 'Full Blood Count'], BillItem::orderBy('id')->pluck('description')->all());
    }

    public function test_insurance_split_uses_provider_price_and_coverage(): void
    {
        $hmo = InsuranceProvider::factory()->create(['coverage_percent' => 90]);
        $this->price(Service::where('code', 'CONSULT')->first(), 4000, $hmo->id);
        $patient = Patient::factory()->create(['payment_type' => 'insurance', 'insurance_provider_id' => $hmo->id, 'insurance_number' => 'H1']);

        $this->visitFor($patient);

        $item = BillItem::sole();
        $this->assertSame(4000.0, $item->amount);
        $this->assertSame(3600.0, $item->insurance_amount);
        $this->assertSame(400.0, $item->patient_amount);
        $this->assertSame('pending', $item->bill->claim_status);
        $this->assertSame($hmo->id, $item->bill->insurance_provider_id);
    }

    public function test_free_patients_are_waived(): void
    {
        $patient = Patient::factory()->create(['payment_type' => 'free']);
        $this->visitFor($patient);

        $this->assertSame(0.0, $patient->outstandingBalance());
        $this->assertSame('Free / waiver patient', BillItem::sole()->discount_reason);
    }

    public function test_cancelled_order_voids_its_charge(): void
    {
        $patient = Patient::factory()->create();
        $consultation = $this->consult($this->visitFor($patient));
        $this->actingAs($this->doctor)->post(route('consultations.lab.store', $consultation), [
            'tests' => [LabTest::where('code', 'FBC')->value('id')], 'priority' => 'routine',
        ]);
        $this->assertSame(5500.0, $patient->outstandingBalance());

        $this->actingAs($this->doctor)->patch(route('orders.cancel', ['lab', LabOrder::first()->id]));

        $this->assertSame(3000.0, $patient->outstandingBalance());
        $this->assertNotNull(BillItem::where('description', 'Full Blood Count')->first()->voided_at);
    }

    public function test_dispensing_charges_drugs_per_unit(): void
    {
        $patient = Patient::factory()->create();
        $consultation = $this->consult($this->visitFor($patient));
        $pcm = Drug::where('name', 'Paracetamol')->where('form', 'Tablet')->first();
        StockBatch::create(['drug_id' => $pcm->id, 'batch_number' => 'B', 'expiry_date' => now()->addYear(), 'quantity_received' => 100, 'quantity_on_hand' => 100]);
        $this->actingAs($this->doctor)->post(route('consultations.prescribe', $consultation), [
            'drug' => $pcm->label, 'dose' => '1g', 'route' => 'Oral', 'frequency' => 'TDS', 'duration_value' => 3, 'duration_unit' => 'days', 'quantity' => 18,
        ]);
        $rx = Prescription::with('items')->first();

        $pharmacist = User::factory()->create()->assignRole('Pharmacist');
        $this->actingAs($pharmacist)->post(route('pharmacy.dispense', $rx), ['lines' => [$rx->items[0]->id => ['quantity' => 18]]])->assertSessionHasNoErrors();

        $item = BillItem::where('billable_type', Drug::class)->sole();
        $this->assertSame(180.0, $item->amount);
        $this->assertSame(18.0, $item->quantity);
    }

    public function test_cashier_takes_item_payments_and_prints_receipt(): void
    {
        $patient = Patient::factory()->create();
        $consultation = $this->consult($this->visitFor($patient));
        $this->actingAs($this->doctor)->post(route('consultations.lab.store', $consultation), [
            'tests' => [LabTest::where('code', 'FBC')->value('id')], 'priority' => 'routine',
        ]);
        $lab = BillItem::where('description', 'Full Blood Count')->first();

        $this->actingAs($this->cashier)->get(route('billing.account', $patient))->assertOk()->assertSee('Full Blood Count');

        // Pay only for the lab test.
        $this->actingAs($this->cashier)->post(route('billing.pay', $patient), [
            'items' => [$lab->id], 'amount' => 2500, 'method' => 'cash',
        ])->assertSessionHas('receipt');

        $payment = Payment::sole();
        $this->assertMatchesRegularExpression('/^RCT\d{4}-000001$/', $payment->receipt_number);
        $this->assertSame(2500.0, $lab->fresh()->paid_amount);
        $this->assertSame(3000.0, $patient->outstandingBalance());

        $this->actingAs($this->cashier)->get(route('billing.receipt', $payment))->assertOk()->assertSee('Full Blood Count')->assertSee($payment->receipt_number);
        $this->actingAs($this->cashier)->get(route('billing.index'))->assertOk()->assertSee($payment->receipt_number);
        $this->actingAs($this->cashier)->get(route('billing.invoice', $lab->bill))->assertOk();

        // Overpayment and missing reference are rejected.
        $consultFee = BillItem::where('description', 'Consultation fee')->first();
        $this->actingAs($this->cashier)->post(route('billing.pay', $patient), ['items' => [$consultFee->id], 'amount' => 5000, 'method' => 'cash'])
            ->assertSessionHasErrors('amount');
        $this->actingAs($this->cashier)->post(route('billing.pay', $patient), ['items' => [$consultFee->id], 'amount' => 3000, 'method' => 'card'])
            ->assertSessionHasErrors('reference');
    }

    public function test_part_payment_fills_oldest_first(): void
    {
        $patient = Patient::factory()->create();
        $consultation = $this->consult($this->visitFor($patient));
        $this->actingAs($this->doctor)->post(route('consultations.lab.store', $consultation), [
            'tests' => [LabTest::where('code', 'FBC')->value('id')], 'priority' => 'routine',
        ]);
        $ids = BillItem::orderBy('id')->pluck('id')->all();

        $this->actingAs($this->cashier)->post(route('billing.pay', $patient), ['items' => $ids, 'amount' => 4000, 'method' => 'cash']);

        $this->assertSame([3000.0, 1000.0], BillItem::orderBy('id')->pluck('paid_amount')->map(fn ($v) => (float) $v)->all());
    }

    public function test_discount_void_and_reversal_need_permission(): void
    {
        $patient = Patient::factory()->create();
        $this->visitFor($patient);
        $item = BillItem::sole();

        $this->actingAs($this->cashier)->post(route('billing.discount', $item), ['discount_amount' => 500, 'discount_reason' => 'x'])->assertForbidden();

        $this->actingAs($this->accountant)->post(route('billing.discount', $item), ['discount_amount' => 5000, 'discount_reason' => 'Too much'])
            ->assertSessionHasErrors('discount_amount');
        $this->actingAs($this->accountant)->post(route('billing.discount', $item), ['discount_amount' => 1000, 'discount_reason' => 'Social welfare'])
            ->assertSessionHas('success');
        $this->assertSame(2000.0, $patient->outstandingBalance());

        $this->actingAs($this->cashier)->post(route('billing.pay', $patient), ['items' => [$item->id], 'amount' => 2000, 'method' => 'cash']);
        $payment = Payment::sole();

        // Paid items can't be voided until the payment is reversed.
        $this->actingAs($this->accountant)->post(route('billing.void-item', $item), ['void_reason' => 'Error'])->assertSessionHasErrors('item');

        $this->actingAs($this->cashier)->post(route('billing.reverse', $payment), ['void_reason' => 'x'])->assertForbidden();
        $this->actingAs($this->accountant)->post(route('billing.reverse', $payment), ['void_reason' => 'Duplicate'])->assertSessionHas('success');
        $this->assertSame(0.0, $item->fresh()->paid_amount);
        $this->assertNotNull($payment->fresh()->voided_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'payment_reversed']);

        $this->actingAs($this->accountant)->post(route('billing.void-item', $item), ['void_reason' => 'Charged in error'])->assertSessionHas('success');
        $this->assertSame(0.0, $patient->outstandingBalance());
    }

    public function test_pay_before_service_blocks_lab_until_paid(): void
    {
        app(Settings::class)->set('bill_before_service', '1');
        $patient = Patient::factory()->create();
        $consultation = $this->consult($this->visitFor($patient));
        $this->actingAs($this->doctor)->post(route('consultations.lab.store', $consultation), [
            'tests' => [LabTest::where('code', 'FBC')->value('id')], 'priority' => 'routine',
        ]);
        $order = LabOrder::first();
        $scientist = User::factory()->create()->assignRole('Lab Scientist');

        $this->actingAs($scientist)->post(route('lab.collect', $order))->assertSessionHasErrors('payment');
        $this->assertSame('requested', $order->fresh()->status);

        // Paying the lab item alone (not the consultation) is enough.
        $lab = BillItem::where('description', 'Full Blood Count')->first();
        $this->actingAs($this->cashier)->post(route('billing.pay', $patient), ['items' => [$lab->id], 'amount' => 2500, 'method' => 'cash']);

        $this->actingAs($scientist)->post(route('lab.collect', $order))->assertSessionHasNoErrors();
        $this->assertSame('collected', $order->fresh()->status);
    }

    public function test_price_list_and_services_catalogue(): void
    {
        $hmo = InsuranceProvider::factory()->create();
        $esr = LabTest::where('code', 'ESR')->first();
        $fbc = LabTest::where('code', 'FBC')->first();

        $this->actingAs($this->accountant)->get(route('billing.prices', ['type' => 'lab-tests']))->assertOk()->assertSee('unpriced');
        $this->actingAs($this->accountant)->post(route('billing.prices.update'), [
            'type' => 'lab-tests', 'prices' => [$esr->id => '800', $fbc->id => ''],
        ])->assertSessionHas('success');
        $this->assertSame(800.0, $esr->priceFor());
        $this->assertNull($fbc->fresh()->priceFor()); // blank removes the price

        $this->actingAs($this->accountant)->post(route('billing.prices.update'), [
            'type' => 'lab-tests', 'provider_id' => $hmo->id, 'prices' => [$esr->id => '700'],
        ]);
        $this->assertSame(700.0, $esr->fresh()->priceFor($hmo->id));
        $this->assertSame(800.0, $esr->fresh()->priceFor());
        $this->actingAs($this->accountant)->get(route('billing.prices', ['type' => 'lab-tests', 'provider_id' => $hmo->id]))->assertOk();

        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->get(route('admin.catalogs.index', 'services'))->assertOk()->assertSee('Registration / card fee');
        $this->actingAs($admin)->post(route('admin.catalogs.store', 'services'), [
            'code' => 'dress', 'name' => 'Wound dressing', 'category' => 'Procedure', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('services', ['code' => 'DRESS']);

        $this->actingAs($this->cashier)->get(route('billing.prices'))->assertForbidden();
    }

    public function test_folder_and_dashboard_show_balance(): void
    {
        $patient = Patient::factory()->create();
        $this->visitFor($patient);

        $this->actingAs($this->cashier)->get(route('patients.show', $patient))->assertOk()->assertSee('Account balance');
        $this->actingAs($this->cashier)->get(route('billing.index'))->assertOk()->assertSee($patient->hospital_number);
    }
}
