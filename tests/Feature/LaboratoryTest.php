<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\QueueService;
use App\Support\Settings;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected User $scientist;

    protected User $supervisor;

    protected Patient $patient;

    protected LabOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->seed(CatalogSeeder::class);

        $this->doctor = User::factory()->create()->assignRole('Doctor');
        $this->scientist = User::factory()->create()->assignRole('Lab Scientist');
        $this->supervisor = User::factory()->create()->assignRole('Lab Scientist');
        $this->patient = Patient::factory()->create();

        // Doctor orders FBC + malaria RDT from a consultation.
        $clinic = Clinic::factory()->create(['requires_triage' => false]);
        $visit = app(QueueService::class)->checkIn($this->patient, ['clinic_id' => $clinic->id], $this->doctor);
        $this->actingAs($this->doctor)->patch(route('visits.move', $visit), ['status' => Visit::IN_CONSULTATION]);
        $this->actingAs($this->doctor)->get(route('consultations.show', $visit));
        $consultation = Consultation::firstOrFail();
        $this->actingAs($this->doctor)->post(route('consultations.lab.store', $consultation), [
            'tests' => LabTest::whereIn('code', ['FBC', 'MRDT'])->pluck('id')->all(), 'priority' => 'urgent',
        ]);
        $this->order = LabOrder::with('items.test.parameters')->firstOrFail();
    }

    protected function item(string $code)
    {
        return $this->order->items->first(fn ($i) => $i->test->code === $code);
    }

    protected function param(string $code, string $name): int
    {
        return $this->item($code)->test->parameters->firstWhere('name', $name)->id;
    }

    protected function enterAll(User $as): void
    {
        $fbc = $this->item('FBC');
        $rdt = $this->item('MRDT');

        $this->actingAs($as)->post(route('lab.results', $this->order), [
            'results' => [
                $fbc->id => [$this->param('FBC', 'Haemoglobin') => '8.2', $this->param('FBC', 'WBC') => '6.1', $this->param('FBC', 'Platelets') => '520'],
                $rdt->id => [$this->param('MRDT', 'Malaria RDT') => 'Positive'],
            ],
            'comments' => [$fbc->id => 'Film comment: microcytic'],
        ])->assertSessionHasNoErrors();
    }

    public function test_starter_parameters_are_seeded(): void
    {
        $this->assertGreaterThanOrEqual(9, LabTest::where('code', 'FBC')->first()->parameters()->count());
        $this->assertSame('4 – 11', $this->item('FBC')->test->parameters->firstWhere('name', 'WBC')->referenceLabel());
    }

    public function test_worklist_and_collection(): void
    {
        $this->actingAs($this->scientist)->get(route('lab.index'))->assertOk()->assertSee($this->order->order_number)->assertSee('Urgent');

        $this->actingAs($this->scientist)->post(route('lab.collect', $this->order))->assertRedirect(route('lab.show', $this->order));
        $this->order->refresh();
        $this->assertSame('collected', $this->order->status);
        $this->assertSame($this->scientist->id, $this->order->collected_by);

        $this->actingAs($this->scientist)->get(route('lab.label', $this->order))->assertOk()->assertSee($this->order->order_number);
        $this->actingAs($this->scientist)->get(route('lab.index', ['tab' => 'bench']))->assertOk()->assertSee($this->order->order_number);

        // Can't collect twice.
        $this->actingAs($this->scientist)->post(route('lab.collect', $this->order))->assertSessionHasErrors('status');
    }

    public function test_results_are_flagged_and_need_second_person_to_verify(): void
    {
        $this->actingAs($this->scientist)->post(route('lab.collect', $this->order));
        $this->enterAll($this->scientist);

        $this->order->refresh()->load('items.results');
        $this->assertSame('in_progress', $this->order->status);

        $fbc = $this->item('FBC')->fresh('results');
        $this->assertSame('low', $fbc->results->firstWhere('name', 'Haemoglobin')->flag);
        $this->assertNull($fbc->results->firstWhere('name', 'WBC')->flag);
        $this->assertSame('high', $fbc->results->firstWhere('name', 'Platelets')->flag);
        $this->assertSame('12 – 17', $fbc->results->firstWhere('name', 'Haemoglobin')->reference); // snapshot
        $this->assertSame('abnormal', $this->item('MRDT')->fresh('results')->results->first()->flag);

        // Same person cannot verify…
        $this->actingAs($this->scientist)->post(route('lab.verify', $this->order))->assertSessionHasErrors('verify');
        $this->assertSame('in_progress', $this->order->fresh()->status);

        // …a colleague can.
        $this->actingAs($this->supervisor)->post(route('lab.verify', $this->order))->assertSessionHas('success');
        $this->order->refresh();
        $this->assertSame('completed', $this->order->status);
        $this->assertNotNull($this->order->completed_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'lab_results_released']);
    }

    public function test_self_verification_allowed_when_setting_enabled(): void
    {
        app(Settings::class)->set('lab_self_verify', '1');
        $this->actingAs($this->scientist)->post(route('lab.collect', $this->order));
        $this->enterAll($this->scientist);

        $this->actingAs($this->scientist)->post(route('lab.verify', $this->order))->assertSessionHas('success');
        $this->assertSame('completed', $this->order->fresh()->status);
    }

    public function test_partial_results_keep_order_on_the_bench(): void
    {
        $this->actingAs($this->scientist)->post(route('lab.collect', $this->order));
        $rdt = $this->item('MRDT');

        $this->actingAs($this->scientist)->post(route('lab.results', $this->order), [
            'results' => [$rdt->id => [$this->param('MRDT', 'Malaria RDT') => 'Negative']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('collected', $this->order->fresh()->status);
        $this->actingAs($this->supervisor)->post(route('lab.verify', $this->order))->assertSessionHasErrors('status');
    }

    public function test_invalid_values_are_rejected_without_saving_anything(): void
    {
        $this->actingAs($this->scientist)->post(route('lab.collect', $this->order));
        $fbc = $this->item('FBC');
        $rdt = $this->item('MRDT');

        $this->actingAs($this->scientist)->post(route('lab.results', $this->order), [
            'results' => [
                $fbc->id => [$this->param('FBC', 'Haemoglobin') => 'abc'],
                $rdt->id => [$this->param('MRDT', 'Malaria RDT') => 'Maybe'],
            ],
        ])->assertSessionHasErrors();

        $this->assertSame(0, \App\Models\LabResult::count());
    }

    public function test_rejecting_a_sample_discards_results(): void
    {
        $this->actingAs($this->scientist)->post(route('lab.collect', $this->order));
        $this->enterAll($this->scientist);

        $this->actingAs($this->scientist)->post(route('lab.reject', $this->order), ['reason' => 'Haemolysed'])->assertRedirect(route('lab.index'));

        $this->order->refresh();
        $this->assertSame('requested', $this->order->status);
        $this->assertSame('Haemolysed', $this->order->rejection_reason);
        $this->assertNull($this->order->collected_at);
        $this->assertSame(0, \App\Models\LabResult::count());
        $this->actingAs($this->scientist)->get(route('lab.show', $this->order))->assertOk()->assertSee('Please recollect');
    }

    public function test_clinicians_only_see_released_results(): void
    {
        $this->actingAs($this->scientist)->post(route('lab.collect', $this->order));
        $this->enterAll($this->scientist);

        // Not yet verified: doctor blocked, lab sees preliminary.
        $this->actingAs($this->doctor)->get(route('lab.report', $this->order))->assertForbidden();
        $this->actingAs($this->scientist)->get(route('lab.report', $this->order))->assertOk()->assertSee('PRELIMINARY');

        $this->actingAs($this->supervisor)->post(route('lab.verify', $this->order));

        $this->actingAs($this->doctor)->get(route('lab.report', $this->order))
            ->assertOk()->assertDontSee('PRELIMINARY')->assertSee('8.2')->assertSee('microcytic');
        $this->actingAs($this->doctor)->get(route('consultations.show', $this->order->visit_id))
            ->assertOk()->assertSee('2 abnormal')->assertSee('8.2');
        $this->actingAs($this->doctor)->get(route('patients.show', $this->patient))
            ->assertOk()->assertSee('Lab results')->assertSee('2 abnormal');
    }

    public function test_free_text_test_without_parameters(): void
    {
        $test = LabTest::where('code', 'URMCS')->first(); // no parameters seeded
        $order = LabOrder::forceCreate([
            'order_number' => 'LABX-1', 'patient_id' => $this->patient->id, 'ordered_by' => $this->doctor->id, 'status' => 'collected',
        ]);
        $item = $order->items()->create(['lab_test_id' => $test->id, 'status' => 'collected']);

        $this->actingAs($this->scientist)->post(route('lab.results', $order), [
            'results' => [$item->id => ['text' => 'E. coli >10^5 CFU/ml, sensitive to nitrofurantoin']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('in_progress', $order->fresh()->status);
        $this->assertStringContainsString('E. coli', $item->results()->first()->value);
    }

    public function test_permissions(): void
    {
        $nurse = User::factory()->create()->assignRole('Nurse');
        $this->actingAs($nurse)->get(route('lab.index'))->assertForbidden();
        $this->actingAs($this->doctor)->post(route('lab.collect', $this->order))->assertForbidden();
    }

    public function test_parameter_admin(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $test = LabTest::where('code', 'CRP')->first();

        $this->actingAs($admin)->get(route('admin.lab-parameters.index', $test))->assertOk()->assertSee('C-reactive protein');
        $this->actingAs($admin)->get(route('admin.catalogs.index', 'lab-tests'))->assertOk();

        $this->actingAs($admin)->post(route('admin.lab-parameters.store', $test), [
            'name' => 'hs-CRP', 'unit' => 'mg/L', 'type' => 'numeric', 'ref_high' => 3,
        ])->assertSessionHasNoErrors();
        $p = $test->parameters()->where('name', 'hs-CRP')->firstOrFail();
        $this->assertSame('< 3', $p->referenceLabel());

        $this->actingAs($admin)->put(route('admin.lab-parameters.update', [$test, $p]), [
            'name' => 'Result', 'type' => 'option', 'options' => 'Positive, Negative', 'ref_text' => 'Negative',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Positive', 'Negative'], $p->fresh()->options);
        $this->assertNull($p->fresh()->ref_high);

        $this->actingAs($admin)->post(route('admin.lab-parameters.store', $test), [
            'name' => 'Bad', 'type' => 'numeric', 'ref_low' => 10, 'ref_high' => 5,
        ])->assertSessionHasErrors('ref_high');

        $this->actingAs($admin)->delete(route('admin.lab-parameters.destroy', [$test, $p]));
        $this->assertModelMissing($p);
    }
}
