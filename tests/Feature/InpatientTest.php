<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Price;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Services\InpatientService;
use App\Services\QueueService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InpatientTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected User $nurse;

    protected Patient $patient;

    protected Ward $ward;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->seed(CatalogSeeder::class);
        Carbon::setTestNow('2026-10-05 10:00:00');

        $this->doctor = User::factory()->create()->assignRole('Doctor');
        $this->nurse = User::factory()->create()->assignRole('Nurse');
        $this->patient = Patient::factory()->create(['gender' => 'female', 'allergies' => null]);

        $bedFee = Service::create(['code' => 'BED-GEN', 'name' => 'General ward bed', 'category' => 'Accommodation']);
        Price::create(['billable_type' => $bedFee->getMorphClass(), 'billable_id' => $bedFee->id, 'amount' => 5000]);

        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->post(route('admin.wards.store'), [
            'name' => 'Female Medical', 'code' => 'fmw', 'type' => 'Female Medical', 'gender' => 'female',
            'service_id' => $bedFee->id, 'is_active' => 1, 'bed_count' => 3, 'bed_prefix' => 'F',
        ])->assertRedirect();
        $this->ward = Ward::firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function admit(?Bed $bed = null): Admission
    {
        $bed ??= $this->ward->beds()->first();
        $this->actingAs($this->doctor)->post(route('inpatients.store', $this->patient), [
            'bed_id' => $bed->id, 'doctor_id' => $this->doctor->id, 'reason' => 'Severe malaria',
        ])->assertRedirect();

        return Admission::latest('id')->firstOrFail();
    }

    public function test_ward_setup_creates_numbered_beds(): void
    {
        $this->assertSame(['F1', 'F2', 'F3'], $this->ward->beds()->pluck('label')->all());
        $this->assertSame('FMW', $this->ward->code);
    }

    public function test_admission_occupies_bed_and_charges_first_day(): void
    {
        $admission = $this->admit();

        $this->assertSame('occupied', $admission->bed->status);
        $this->assertMatchesRegularExpression('/^ADM2026-000001$/', $admission->admission_number);
        $bill = Bill::where('admission_id', $admission->id)->firstOrFail();
        $this->assertSame(5000.0, $bill->totals()['amount']);
        $this->assertSame('2026-10-05', $admission->bed_charged_until->toDateString());

        $this->actingAs($this->nurse)->get(route('inpatients.index'))->assertOk()->assertSee($this->patient->list_name);
        $this->actingAs($this->nurse)->get(route('inpatients.show', $admission))->assertOk()->assertSee('Severe malaria');
        $this->actingAs($this->nurse)->get(route('patients.show', $this->patient))->assertOk()->assertSee('Open inpatient chart');
    }

    public function test_cannot_double_admit_or_use_occupied_or_wrong_gender_bed(): void
    {
        $admission = $this->admit();

        $this->actingAs($this->doctor)->post(route('inpatients.store', $this->patient), [
            'bed_id' => $this->ward->beds()->skip(1)->first()->id, 'reason' => 'x',
        ])->assertSessionHasErrors('patient');

        $man = Patient::factory()->create(['gender' => 'male']);
        $this->actingAs($this->doctor)->post(route('inpatients.store', $man), [
            'bed_id' => $this->ward->beds()->skip(1)->first()->id, 'reason' => 'x',
        ])->assertSessionHasErrors('bed_id');

        $other = Patient::factory()->create(['gender' => 'female']);
        $this->actingAs($this->doctor)->post(route('inpatients.store', $other), [
            'bed_id' => $admission->bed_id, 'reason' => 'x',
        ])->assertSessionHasErrors('bed_id');
    }

    public function test_nightly_bed_charges_catch_up_once_per_day(): void
    {
        $admission = $this->admit();

        Carbon::setTestNow('2026-10-08 00:05:00');
        $this->artisan('emr:charge-beds')->assertSuccessful();
        $this->artisan('emr:charge-beds')->assertSuccessful(); // idempotent

        $this->assertSame(4, BillItem::where('source_type', $admission->getMorphClass())->count()); // 5th–8th
        $this->assertSame('2026-10-08', $admission->fresh()->bed_charged_until->toDateString());
        $this->assertSame(4, $admission->fresh()->lengthOfStay());
    }

    public function test_transfer_moves_bed_and_keeps_history(): void
    {
        $admission = $this->admit();
        $old = $admission->bed;
        $new = $this->ward->beds()->where('label', 'F3')->first();

        $this->actingAs($this->nurse)->post(route('inpatients.transfer', $admission), ['bed_id' => $new->id, 'reason' => 'Closer to station'])
            ->assertSessionHas('success');

        $this->assertSame($new->id, $admission->fresh()->bed_id);
        $this->assertSame('occupied', $new->fresh()->status);
        $this->assertSame('cleaning', $old->fresh()->status);
        $this->assertSame(2, $admission->movements()->count());
    }

    public function test_notes_drug_chart_and_administration(): void
    {
        $admission = $this->admit();

        $this->actingAs($this->doctor)->post(route('inpatients.notes', $admission), ['type' => 'ward_round', 'note' => 'Improving, afebrile.'])
            ->assertSessionHas('success');
        $this->actingAs($this->nurse)->post(route('inpatients.notes', $admission), ['type' => 'nursing', 'note' => 'Slept well.']);
        $this->assertSame(2, $admission->notes()->count());

        $this->actingAs($this->doctor)->post(route('inpatients.prescribe', $admission), [
            'drug' => 'Artesunate 60mg Injection', 'dose' => '120mg', 'route' => 'IV', 'frequency' => 'BD', 'duration_value' => 3, 'duration_unit' => 'days',
        ])->assertSessionHasNoErrors();

        $rx = Prescription::with('items')->sole();
        $this->assertSame($admission->id, $rx->admission_id);
        $item = $rx->items[0];

        // Nurse records doses; refusals need a reason.
        $this->actingAs($this->nurse)->post(route('inpatients.administer', $admission), ['prescription_item_id' => $item->id, 'status' => 'given', 'dose_given' => '120mg'])
            ->assertSessionHas('success');
        $this->actingAs($this->nurse)->post(route('inpatients.administer', $admission), ['prescription_item_id' => $item->id, 'status' => 'refused'])
            ->assertSessionHasErrorsIn('mar', 'note');
        $this->assertSame(1, MedicationAdministration::count());

        // Doctors can't chart doses; nurses can't prescribe.
        $this->actingAs($this->doctor)->post(route('inpatients.administer', $admission), ['prescription_item_id' => $item->id, 'status' => 'given'])->assertForbidden();
        $this->actingAs($this->nurse)->post(route('inpatients.prescribe', $admission), ['drug' => 'x'])->assertForbidden();

        $this->actingAs($this->doctor)->post(route('inpatients.stop-medication', $item))->assertRedirect();
        $this->assertNotNull($item->fresh()->stopped_at);
        $this->actingAs($this->nurse)->post(route('inpatients.administer', $admission), ['prescription_item_id' => $item->id, 'status' => 'given'])
            ->assertSessionHasErrorsIn('mar', 'prescription_item_id');

        $this->actingAs($this->nurse)->get(route('inpatients.show', $admission))->assertOk()->assertSee('Artesunate')->assertSee('Slept well.');
    }

    public function test_ward_orders_are_billed_to_the_admission(): void
    {
        Price::create(['billable_type' => (new LabTest)->getMorphClass(), 'billable_id' => LabTest::where('code', 'FBC')->value('id'), 'amount' => 2500]);
        $admission = $this->admit();

        $this->actingAs($this->doctor)->post(route('inpatients.lab', $admission), [
            'tests' => [LabTest::where('code', 'FBC')->value('id')], 'priority' => 'urgent',
        ])->assertSessionHas('success');

        $order = LabOrder::sole();
        $this->assertSame($admission->id, $order->admission_id);
        $this->assertSame(7500.0, Bill::where('admission_id', $admission->id)->first()->totals()['amount']);

        $this->actingAs($this->doctor)->patch(route('inpatients.cancel-order', ['lab', $order->id]))->assertSessionHas('success');
        $this->assertSame(5000.0, Bill::where('admission_id', $admission->id)->first()->totals()['amount']);
    }

    public function test_discharge_requires_balance_acknowledgement_and_frees_bed(): void
    {
        $admission = $this->admit();
        Carbon::setTestNow('2026-10-07 09:00:00');
        $payload = ['discharge_type' => 'home', 'final_diagnosis' => 'Severe malaria, resolved', 'discharge_summary' => 'Treated with IV artesunate.'];

        $this->actingAs($this->doctor)->get(route('inpatients.discharge', $admission))->assertOk()->assertSee('The patient owes');
        $this->actingAs($this->doctor)->post(route('inpatients.discharge.store', $admission), $payload)->assertSessionHasErrors('balance_acknowledged');
        $this->assertTrue($admission->fresh()->isCurrent());

        $this->actingAs($this->doctor)->post(route('inpatients.discharge.store', $admission), $payload + ['balance_acknowledged' => 1])
            ->assertRedirect(route('inpatients.summary', $admission));

        $admission->refresh();
        $this->assertSame('discharged', $admission->status);
        $this->assertSame('cleaning', $admission->bed->status);
        $this->assertSame(3, BillItem::where('source_type', $admission->getMorphClass())->count()); // 5th, 6th, 7th
        $this->assertSame(3, $admission->lengthOfStay());

        $this->actingAs($this->doctor)->get(route('inpatients.summary', $admission))->assertOk()->assertSee('IV artesunate');
        $this->actingAs($this->nurse)->post(route('inpatients.discharge.store', $admission), $payload)->assertForbidden(); // nurses can't discharge
    }

    public function test_death_on_discharge_marks_patient_deceased(): void
    {
        $admission = $this->admit();
        app(InpatientService::class)->discharge($admission, [
            'discharge_type' => 'died', 'final_diagnosis' => 'x', 'discharge_summary' => 'x',
        ], $this->doctor);

        $this->assertTrue($this->patient->fresh()->is_deceased);
    }

    public function test_deposit_is_held_then_used_to_pay(): void
    {
        $cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($cashier)->post(route('billing.deposit', $this->patient), ['amount' => 20000, 'method' => 'cash'])
            ->assertSessionHas('receipt');
        $this->actingAs($cashier)->get(route('billing.receipt', Payment::sole()))->assertOk()->assertSee('Deposit');

        $admission = $this->admit();
        $bedItem = BillItem::sole();

        $this->actingAs($cashier)->get(route('billing.account', $this->patient))->assertOk()->assertSee('From deposit');
        $this->actingAs($cashier)->post(route('billing.pay', $this->patient), ['items' => [$bedItem->id], 'amount' => 5000, 'method' => 'deposit'])
            ->assertSessionHasNoErrors();

        $this->assertSame(5000.0, $bedItem->fresh()->paid_amount);
        $this->assertSame(1, Payment::count()); // no new money received
        $this->assertSame(15000.0, app(\App\Services\BillingService::class)->depositBalance($this->patient));

        $this->actingAs($cashier)->post(route('billing.pay', $this->patient), ['items' => [$bedItem->id], 'amount' => 1, 'method' => 'deposit'])
            ->assertSessionHasErrors('amount'); // nothing left owing on that item
    }

    public function test_admit_from_consultation_and_permissions(): void
    {
        $clinic = Clinic::factory()->create(['requires_triage' => false]);
        $visit = app(QueueService::class)->checkIn($this->patient, ['clinic_id' => $clinic->id], $this->doctor);
        $this->actingAs($this->doctor)->patch(route('visits.move', $visit), ['status' => Visit::IN_CONSULTATION]);
        $this->actingAs($this->doctor)->get(route('consultations.show', $visit))->assertOk()->assertSee('Admit patient');
        $c = Consultation::sole();
        $this->actingAs($this->doctor)->post(route('consultations.diagnoses.store', $c), ['description' => 'Severe anaemia', 'certainty' => 'confirmed']);

        $this->actingAs($this->doctor)->get(route('inpatients.create', ['patient' => $this->patient, 'visit' => $visit->id]))
            ->assertOk()->assertSee('Severe anaemia');

        $cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($cashier)->get(route('inpatients.create', $this->patient))->assertForbidden();
        $this->actingAs($cashier)->get(route('inpatients.index'))->assertOk();
        $this->actingAs($this->doctor)->get(route('admin.wards.index'))->assertForbidden();

        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->get(route('admin.wards.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.wards.edit', $this->ward))->assertOk();
        $this->actingAs($admin)->post(route('admin.wards.beds', $this->ward), ['bed_count' => 2, 'bed_prefix' => 'F'])->assertSessionHas('success');
        $this->assertSame(['F1', 'F2', 'F3', 'F4', 'F5'], $this->ward->beds()->pluck('label')->all());
    }
}
