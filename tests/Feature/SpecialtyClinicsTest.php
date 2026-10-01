<?php

namespace Tests\Feature;

use App\Models\BillItem;
use App\Models\Clinic;
use App\Models\DentalFinding;
use App\Models\EyeExam;
use App\Models\Patient;
use App\Models\PhysioEpisode;
use App\Models\Price;
use App\Models\Service;
use App\Models\User;
use App\Services\QueueService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyClinicsTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->seed(CatalogSeeder::class);

        $this->doctor = User::factory()->create()->assignRole('Doctor');
        $this->patient = Patient::factory()->create(['date_of_birth' => now()->subYears(40)]);
    }

    protected function price(string $code, float $amount): Service
    {
        $service = Service::where('code', $code)->firstOrFail();
        Price::create(['billable_type' => $service->getMorphClass(), 'billable_id' => $service->id, 'amount' => $amount]);

        return $service;
    }

    public function test_dental_chart_findings_plan_and_completion_charges(): void
    {
        $extraction = $this->price('DEN-EXT', 5000);
        $visit = app(QueueService::class)->checkIn($this->patient, ['clinic_id' => Clinic::factory()->create(['specialty' => 'dental'])->id], $this->doctor);

        $this->actingAs($this->doctor)->get(route('specialty.dental', $this->patient))->assertOk()->assertSee('Dental chart')->assertSee('aria-label="Tooth 36: nothing recorded"', false);

        $this->actingAs($this->doctor)->post(route('specialty.dental.store', $this->patient), [
            'tooth' => 36, 'surfaces' => ['O', 'D'], 'condition' => 'caries', 'status' => 'existing',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->doctor)->post(route('specialty.dental.store', $this->patient), [
            'tooth' => 36, 'condition' => 'extracted', 'status' => 'planned', 'service_id' => $extraction->id,
        ])->assertSessionHasNoErrors();

        // A plan needs a treatment; an invalid tooth number is refused.
        $this->actingAs($this->doctor)->post(route('specialty.dental.store', $this->patient), ['tooth' => 36, 'status' => 'planned'])->assertSessionHasErrors('service_id');
        $this->actingAs($this->doctor)->post(route('specialty.dental.store', $this->patient), ['tooth' => 19, 'condition' => 'caries', 'status' => 'existing'])->assertSessionHasErrors('tooth');

        $this->actingAs($this->doctor)->get(route('specialty.dental', $this->patient))->assertSee('Tooth 36: Caries, treatment planned');
        // Planning does not charge; only completion does.
        $this->assertSame(0, BillItem::where('billable_type', $extraction->getMorphClass())->where('billable_id', $extraction->id)->count());

        $plan = DentalFinding::where('status', 'planned')->sole();
        $this->actingAs($this->doctor)->patch(route('specialty.dental.complete', $plan))->assertSessionHasNoErrors();
        $this->assertSame('completed', $plan->fresh()->status);

        $charge = BillItem::whereMorphedTo('source', $plan)->sole();
        $this->assertEquals(5000, $charge->amount);
        $this->assertSame($visit->id, $charge->bill->visit_id);
        $this->assertStringContainsString('tooth 36', $charge->description);
        $this->actingAs($this->doctor)->get(route('specialty.dental', $this->patient))->assertSee('Tooth 36: Extracted');

        // Cannot complete twice.
        $this->actingAs($this->doctor)->patch(route('specialty.dental.complete', $plan))->assertSessionHasErrors('status');
    }

    public function test_eye_exam_alerts_and_spectacle_prescription(): void
    {
        $this->price(Service::EYE_REFRACTION, 3000);

        $this->actingAs($this->doctor)->post(route('specialty.eye.store', $this->patient), [
            'va_right' => '6/18', 'va_left' => '6/60', 'va_right_corrected' => '6/6', 'va_left_corrected' => '6/60',
            'iop_right' => 16, 'iop_left' => 28,
            'sph_right' => -1.25, 'cyl_right' => -0.5, 'axis_right' => 180, 'sph_left' => -1.0,
            'add_right' => 1.5, 'add_left' => 1.5, 'pd' => 62,
            'diagnosis' => 'Myopia; glaucoma suspect (left)', 'spectacles_prescribed' => '1', 'charge_refraction' => '1',
        ])->assertSessionHas('warning');

        $exam = EyeExam::sole();
        $this->assertCount(2, $exam->alerts()); // left IOP 28, left best VA 6/60
        $this->assertSame('-1.25 DS / -0.50 DC × 180° · Add +1.50', $exam->rx('right'));
        $this->assertEquals(3000, BillItem::whereMorphedTo('source', $exam)->sole()->amount);

        // Cylinder without axis is refused.
        $this->actingAs($this->doctor)->post(route('specialty.eye.store', $this->patient), ['cyl_left' => -1])->assertSessionHasErrors('axis_left');

        $this->actingAs($this->doctor)->get(route('specialty.eye', $this->patient))->assertOk()->assertSee('Left eye pressure 28');
        $this->actingAs($this->doctor)->get(route('specialty.spectacles', $exam))->assertOk()->assertSee('SPECTACLE PRESCRIPTION')->assertSee('-1.25');
    }

    public function test_physio_course_sessions_chart_and_discharge(): void
    {
        $physio = User::factory()->create()->assignRole('Physiotherapist');
        $this->price(Service::PHYSIO_SESSION, 2000);

        $this->actingAs($physio)->post(route('specialty.physio.store', $this->patient), [
            'region' => 'Lower back', 'complaint' => 'Back pain for 3 months', 'pain_initial' => 8, 'sessions_planned' => 6,
        ])->assertSessionHasNoErrors();
        $episode = PhysioEpisode::sole();

        foreach ([[8, 6], [6, 4], [4, 2]] as $i => [$before, $after]) {
            $this->actingAs($physio)->post(route('specialty.physio.session', $episode), [
                'session_date' => today()->subDays(3 - $i)->toDateString(), 'treatments' => ['exercise', 'tens'],
                'pain_before' => $before, 'pain_after' => $after,
            ])->assertSessionHasNoErrors();
        }
        $this->actingAs($physio)->post(route('specialty.physio.session', $episode), ['session_date' => today()->toDateString()])
            ->assertSessionHasErrors('treatments');

        $this->assertSame(2, $episode->fresh()->load('sessions')->latestPain());
        $this->assertSame(3, BillItem::where('description', 'like', 'Physiotherapy session%')->count());
        $this->actingAs($physio)->get(route('specialty.physio.show', $episode))->assertOk()
            ->assertSee('data-trend-chart', false)->assertSee('Exercise therapy, TENS');

        $this->actingAs($physio)->post(route('specialty.physio.discharge', $episode), ['outcome' => 'improved'])->assertSessionHasNoErrors();
        $this->actingAs($physio)->post(route('specialty.physio.session', $episode), ['session_date' => today()->toDateString(), 'treatments' => ['exercise']])
            ->assertSessionHasErrors('status');

        $this->actingAs($this->doctor)->get(route('reports.show', ['report' => 'specialty']))->assertOk()->assertSee('Physiotherapy sessions');
    }

    public function test_permissions_clinic_type_and_consultation_link(): void
    {
        $optometrist = User::factory()->create()->assignRole('Optometrist');
        $this->actingAs($optometrist)->post(route('specialty.dental.store', $this->patient), ['condition' => 'caries', 'status' => 'existing'])->assertForbidden();
        $this->actingAs($optometrist)->get(route('specialty.eye', $this->patient))->assertOk()->assertSee('New eye examination');
        $this->actingAs($optometrist)->get(route('specialty.dental', $this->patient))->assertOk()->assertDontSee('Add to chart');

        $cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($cashier)->get(route('specialty.eye', $this->patient))->assertForbidden();

        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->post(route('admin.clinics.store'), ['name' => 'Eye Clinic', 'code' => 'EYE', 'specialty' => 'eye', 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $clinic = Clinic::where('code', 'EYE')->sole();
        $this->assertSame('eye', $clinic->specialty);

        $clinic->update(['requires_triage' => false]);
        $visit = app(QueueService::class)->checkIn($this->patient, ['clinic_id' => $clinic->id], $this->doctor);
        $this->actingAs($this->doctor)->patch(route('visits.move', $visit), ['status' => \App\Models\Visit::IN_CONSULTATION]);
        $this->actingAs($this->doctor)->get(route('consultations.show', $visit))->assertOk()->assertSee('Open eye examination');

        $this->actingAs($this->doctor)->get(route('patients.show', $this->patient))->assertOk()->assertSee('Specialty clinics');
    }
}
