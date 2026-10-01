<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\ImagingTest;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Services\QueueService;
use App\Support\AllergyChecker;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected Patient $patient;

    protected Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->seed(CatalogSeeder::class);

        $this->doctor = User::factory()->create()->assignRole('Doctor');
        $this->patient = Patient::factory()->create(['allergies' => 'Penicillin']);
        $clinic = Clinic::factory()->create(['requires_triage' => false]);
        $this->visit = app(QueueService::class)->checkIn($this->patient, ['clinic_id' => $clinic->id, 'complaint' => 'Fever'], $this->doctor);
    }

    /** Start the consultation from the queue, as the doctor would. */
    protected function start(): Consultation
    {
        $this->actingAs($this->doctor)
            ->patch(route('visits.move', $this->visit), ['status' => Visit::IN_CONSULTATION])
            ->assertRedirect(route('consultations.show', $this->visit));
        $this->actingAs($this->doctor)->get(route('consultations.show', $this->visit))->assertOk();

        return Consultation::where('visit_id', $this->visit->id)->firstOrFail();
    }

    public function test_starting_creates_a_draft_owned_by_the_doctor(): void
    {
        $c = $this->start();

        $this->assertSame($this->doctor->id, $c->doctor_id);
        $this->assertSame('draft', $c->status);
        $this->assertSame('Fever', $c->presenting_complaint); // carried over from check-in
        $this->assertSame($this->doctor->id, $this->visit->fresh()->doctor_id);
    }

    public function test_viewing_without_consultation_does_not_create_one(): void
    {
        $nurse = User::factory()->create()->assignRole('Nurse');
        $this->actingAs($nurse)->get(route('consultations.show', $this->visit))->assertOk()->assertSee('No consultation');
        $this->actingAs($this->doctor)->get(route('consultations.show', $this->visit))->assertOk()->assertSee('Start consultation');
        $this->assertSame(0, Consultation::count());
    }

    public function test_notes_and_diagnoses(): void
    {
        $c = $this->start();

        $this->actingAs($this->doctor)->put(route('consultations.update', $c), [
            'presenting_complaint' => 'Fever x3 days', 'examination' => 'Temp 38.5, pale',
        ])->assertRedirect();
        $this->assertSame('Temp 38.5, pale', $c->fresh()->examination);

        $this->actingAs($this->doctor)->post(route('consultations.diagnoses.store', $c), [
            'icd10_code' => 'B50.9', 'description' => 'Plasmodium falciparum malaria, unspecified', 'certainty' => 'confirmed',
        ]);
        $this->actingAs($this->doctor)->post(route('consultations.diagnoses.store', $c), [
            'description' => 'Possible typhoid', 'certainty' => 'differential',
        ]);

        $dx = $c->diagnoses()->get();
        $this->assertCount(2, $dx);
        $this->assertTrue($dx[0]->is_primary); // first becomes primary automatically
        $this->assertNull($dx[1]->icd10_code); // free text allowed

        $this->actingAs($this->doctor)->post(route('consultations.diagnoses.store', $c), ['icd10_code' => 'XXX', 'description' => 'x', 'certainty' => 'confirmed'])
            ->assertSessionHasErrorsIn('diagnosis', 'icd10_code');
    }

    public function test_icd10_search(): void
    {
        $this->actingAs($this->doctor)->getJson(route('icd10.search', ['q' => 'malaria falciparum']))
            ->assertOk()->assertJsonFragment(['code' => 'B50.9']);
        $this->actingAs($this->doctor)->getJson(route('icd10.search', ['q' => 'I10']))
            ->assertOk()->assertJsonPath('0.code', 'I10');
    }

    public function test_lab_and_imaging_orders(): void
    {
        $c = $this->start();
        $fbc = LabTest::where('code', 'FBC')->first();
        $mps = LabTest::where('code', 'MPS')->first();

        $this->actingAs($this->doctor)->post(route('consultations.lab.store', $c), [
            'tests' => [$fbc->id, $mps->id], 'priority' => 'urgent', 'clinical_notes' => '?malaria',
        ])->assertSessionHas('success');

        $order = LabOrder::firstOrFail();
        $this->assertMatchesRegularExpression('/^LAB\d{4}-000001$/', $order->order_number);
        $this->assertSame(2, $order->items()->count());
        $this->assertSame($this->visit->id, $order->visit_id);

        $this->actingAs($this->doctor)->post(route('consultations.lab.store', $c), ['tests' => [], 'priority' => 'routine'])
            ->assertSessionHasErrorsIn('lab', 'tests');

        $xray = ImagingTest::where('code', 'XR-CHEST')->first();
        $this->actingAs($this->doctor)->post(route('consultations.imaging.store', $c), [
            'imaging_test_id' => $xray->id, 'priority' => 'routine', 'clinical_notes' => 'Chronic cough',
        ])->assertSessionHas('success');

        $this->actingAs($this->doctor)->patch(route('orders.cancel', ['lab', $order->id]))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_prescribing_with_formulary_and_free_text(): void
    {
        $c = $this->start();

        $this->actingAs($this->doctor)->post(route('consultations.prescribe', $c), [
            'drug' => 'Artemether/Lumefantrine 20/120mg Tablet', 'dose' => '4 tabs', 'route' => 'Oral',
            'frequency' => 'BD', 'duration_value' => 3, 'duration_unit' => 'days', 'quantity' => 24,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->doctor)->post(route('consultations.prescribe', $c), [
            'drug' => 'Some Local Syrup', 'dose' => '10ml', 'route' => 'Oral', 'frequency' => 'TDS', 'duration_value' => 5, 'duration_unit' => 'days',
        ])->assertSessionHasNoErrors();

        $rx = Prescription::with('items')->sole(); // both items on one prescription
        $this->assertSame(Drug::where('name', 'Artemether/Lumefantrine')->value('id'), $rx->items[0]->drug_id);
        $this->assertNull($rx->items[1]->drug_id);
        $this->assertSame('4 tabs Oral BD × 3 days', $rx->items[0]->directions());

        $this->actingAs($this->doctor)->get(route('prescriptions.print', $rx))->assertOk()->assertSee('Penicillin')->assertSee('Some Local Syrup');
    }

    public function test_allergy_check_blocks_until_overridden(): void
    {
        $c = $this->start();
        $payload = ['drug' => 'Amoxicillin 500mg Capsule', 'dose' => '500mg', 'route' => 'Oral', 'frequency' => 'TDS', 'duration_value' => 5, 'duration_unit' => 'days'];

        $this->actingAs($this->doctor)->post(route('consultations.prescribe', $c), $payload)->assertSessionHasErrorsIn('rx', 'drug');
        $this->assertSame(0, Prescription::count());

        $this->actingAs($this->doctor)->post(route('consultations.prescribe', $c), $payload + ['allergy_override' => 1])->assertSessionHasNoErrors();
        $this->assertTrue(Prescription::first()->items()->first()->allergy_override);
        $this->assertDatabaseHas('audit_logs', ['event' => 'allergy_override']);
    }

    public function test_allergy_checker_classes(): void
    {
        $p = new Patient(['allergies' => 'Sulfa drugs; aspirin']);
        $this->assertNotEmpty(AllergyChecker::conflicts($p, 'Cotrimoxazole 960mg Tablet'));
        $this->assertNotEmpty(AllergyChecker::conflicts($p, 'Ibuprofen 400mg Tablet'));
        $this->assertEmpty(AllergyChecker::conflicts($p, 'Paracetamol 500mg Tablet'));
        $this->assertEmpty(AllergyChecker::conflicts(new Patient(['allergies' => 'None']), 'Amoxicillin'));
    }

    public function test_signing_requires_complaint_and_diagnosis(): void
    {
        $c = $this->start();
        $c->update(['presenting_complaint' => null]);

        $this->actingAs($this->doctor)->post(route('consultations.sign', $c))->assertSessionHasErrors(['presenting_complaint', 'diagnosis']);
        $this->assertSame('draft', $c->fresh()->status);
    }

    public function test_signing_locks_completes_visit_and_books_follow_up(): void
    {
        $c = $this->start();
        $this->actingAs($this->doctor)->post(route('consultations.diagnoses.store', $c), ['description' => 'Malaria', 'certainty' => 'confirmed']);

        $this->actingAs($this->doctor)->post(route('consultations.sign', $c), [
            'followup_date' => now()->addWeek()->toDateString(), 'followup_time' => '10:00',
        ])->assertRedirect(route('queue.index', ['mine' => 1]));

        $c->refresh();
        $this->assertSame('signed', $c->status);
        $this->assertNotNull($c->signed_at);
        $this->assertSame(Visit::COMPLETED, $this->visit->fresh()->status);

        $appt = Appointment::sole();
        $this->assertSame('follow_up', $appt->type);
        $this->assertSame('Follow-up: Malaria', $appt->reason);
        $this->assertSame($this->doctor->id, $appt->doctor_id);

        // Locked now.
        $this->actingAs($this->doctor)->put(route('consultations.update', $c), ['plan' => 'changed'])->assertForbidden();
        $this->actingAs($this->doctor)->post(route('consultations.diagnoses.store', $c), ['description' => 'x', 'certainty' => 'confirmed'])->assertForbidden();

        // Addenda are the way to add information.
        $this->actingAs($this->doctor)->post(route('consultations.addenda.store', $c), ['note' => 'Lab confirmed MP+'])->assertSessionHas('success');
        $this->actingAs($this->doctor)->get(route('consultations.show', $this->visit))->assertOk()->assertSee('Lab confirmed MP+')->assertSee('Signed by');
    }

    public function test_other_doctor_cannot_edit(): void
    {
        $c = $this->start();
        $other = User::factory()->create()->assignRole('Doctor');

        $this->actingAs($other)->get(route('consultations.show', $this->visit))->assertOk()->assertSee('read only for you');
        $this->actingAs($other)->put(route('consultations.update', $c), ['plan' => 'x'])->assertForbidden();
    }

    public function test_nurse_can_view_but_not_write(): void
    {
        $c = $this->start();
        $nurse = User::factory()->create()->assignRole('Nurse');

        $this->actingAs($nurse)->get(route('consultations.show', $this->visit))->assertOk();
        $this->actingAs($nurse)->put(route('consultations.update', $c), ['plan' => 'x'])->assertForbidden();

        $records = User::factory()->create()->assignRole('Records Officer');
        $this->actingAs($records)->get(route('consultations.show', $this->visit))->assertForbidden();
    }

    public function test_queue_and_folder_link_to_consultation(): void
    {
        $c = $this->start();
        $this->actingAs($this->doctor)->get(route('queue.index'))->assertOk()->assertSee('Open consultation');

        $this->actingAs($this->doctor)->post(route('consultations.diagnoses.store', $c), ['description' => 'Malaria', 'certainty' => 'confirmed']);
        $this->actingAs($this->doctor)->post(route('consultations.sign', $c));

        $this->actingAs($this->doctor)->get(route('patients.show', $this->patient))
            ->assertOk()->assertSee('Confirmed diagnoses')->assertSee('Malaria');
    }

    public function test_catalog_admin(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        foreach (['lab-tests', 'imaging', 'drugs'] as $catalog) {
            $this->actingAs($admin)->get(route('admin.catalogs.index', $catalog))->assertOk();
            $this->actingAs($admin)->get(route('admin.catalogs.create', $catalog))->assertOk();
        }

        $this->actingAs($admin)->post(route('admin.catalogs.store', 'lab-tests'), [
            'code' => 'ferr', 'name' => 'Serum Ferritin', 'category' => 'Chemistry', 'is_active' => 1,
        ])->assertRedirect(route('admin.catalogs.index', 'lab-tests'));
        $test = LabTest::where('code', 'FERR')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.catalogs.edit', ['lab-tests', $test->id]))->assertOk();
        $this->actingAs($admin)->put(route('admin.catalogs.update', ['lab-tests', $test->id]), [
            'code' => 'FERR', 'name' => 'Serum Ferritin', 'category' => 'Chemistry', 'is_active' => 0,
        ]);
        $this->assertFalse($test->fresh()->is_active);

        $this->actingAs($admin)->post(route('admin.catalogs.store', 'lab-tests'), ['code' => 'FBC', 'name' => 'Dup', 'category' => 'X'])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->doctor)->get(route('admin.catalogs.index', 'drugs'))->assertForbidden();
        $this->actingAs($admin)->get('/admin/catalogs/unknown')->assertNotFound();
    }

    public function test_import_icd10_command(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'icd');
        file_put_contents($file, "code,description\nZ99.9,Test code\n");

        $this->artisan('emr:import-icd10', ['file' => $file])->assertSuccessful();
        $this->assertDatabaseHas('icd10_codes', ['code' => 'Z99.9']);
        unlink($file);
    }
}
