<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\NursingNote;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use App\Services\QueueService;
use App\Support\VitalAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NursingTriageTest extends TestCase
{
    use RefreshDatabase;

    protected User $nurse;

    protected Patient $patient;

    protected Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();

        $this->nurse = User::factory()->create()->assignRole('Nurse');
        $this->patient = Patient::factory()->create(['date_of_birth' => now()->subYears(45)]);
        $clinic = Clinic::factory()->create(['requires_triage' => true]);
        $this->visit = app(QueueService::class)->checkIn($this->patient, ['clinic_id' => $clinic->id], $this->nurse);
    }

    protected function normalVitals(array $overrides = []): array
    {
        return array_merge([
            'temperature' => 36.8, 'systolic' => 120, 'diastolic' => 80, 'pulse' => 72,
            'respiratory_rate' => 16, 'spo2' => 98, 'weight' => 70, 'height' => 175, 'consciousness' => 'A',
        ], $overrides);
    }

    protected function vitalsFor(array $values, ?int $age = 45): VitalSign
    {
        $patient = Patient::factory()->make(['date_of_birth' => $age === null ? null : now()->subYears($age)]);
        $v = new VitalSign($values);
        $v->recorded_at = now();
        $v->setRelation('patient', $patient);

        return $v;
    }

    public function test_triage_records_vitals_and_sends_to_doctor(): void
    {
        $this->actingAs($this->nurse)->post(route('vitals.triage.store', $this->visit), $this->normalVitals([
            'priority' => 'urgent', 'send_to_doctor' => 1, 'nursing_note' => 'Came in walking, anxious.',
        ]))->assertRedirect(route('vitals.worklist'));

        $v = VitalSign::firstOrFail();
        $this->assertSame($this->visit->id, $v->visit_id);
        $this->assertSame($this->nurse->id, $v->recorded_by);
        $this->assertSame(22.9, $v->bmi);
        $this->assertSame(0, $v->news2_score);

        $this->visit->refresh();
        $this->assertSame(Visit::WAITING_DOCTOR, $this->visit->status);
        $this->assertSame('urgent', $this->visit->priority);
        $this->assertSame('triage', NursingNote::first()->type);
    }

    public function test_triage_can_keep_patient_waiting(): void
    {
        $this->actingAs($this->nurse)->post(route('vitals.triage.store', $this->visit), $this->normalVitals(['priority' => 'normal', 'send_to_doctor' => 0]));

        $this->assertSame(Visit::WAITING_TRIAGE, $this->visit->fresh()->status);
    }

    public function test_validation(): void
    {
        $this->actingAs($this->nurse)->post(route('vitals.triage.store', $this->visit), ['priority' => 'normal'])
            ->assertSessionHasErrors('temperature'); // nothing entered

        $this->actingAs($this->nurse)->post(route('vitals.triage.store', $this->visit), [
            'priority' => 'normal', 'systolic' => 80, 'diastolic' => 120, 'spo2' => 140, 'temperature' => 60,
        ])->assertSessionHasErrors(['diastolic', 'spo2', 'temperature']);

        $this->actingAs($this->nurse)->post(route('vitals.triage.store', $this->visit), ['priority' => 'normal', 'systolic' => 120])
            ->assertSessionHasErrors('diastolic'); // BP needs both numbers
    }

    public function test_news2_scoring(): void
    {
        $this->assertSame(0, VitalAssessment::news2($this->vitalsFor($this->normalVitals()), 45));

        // RR 25 (3) + SpO2 91 (3) + O2 (2) + SBP 95 (2) + HR 115 (2) + Confused (3) + Temp 39.2 (2) = 17
        $sick = $this->vitalsFor(['respiratory_rate' => 25, 'spo2' => 91, 'on_oxygen' => true, 'systolic' => 95,
            'diastolic' => 60, 'pulse' => 115, 'consciousness' => 'C', 'temperature' => 39.2]);
        $this->assertSame(17, VitalAssessment::news2($sick, 45));
        $this->assertSame('high', $sick->news2Risk()['level']);

        // Single parameter of 3 with low total → low-medium.
        $single = $this->vitalsFor($this->normalVitals(['respiratory_rate' => 7]));
        $this->assertSame(3, $single->news2Risk()['score']);
        $this->assertSame('low-medium', $single->news2Risk()['level']);

        // Incomplete set or child → not calculated.
        $this->assertNull(VitalAssessment::news2($this->vitalsFor(['pulse' => 80]), 45));
        $this->assertNull(VitalAssessment::news2($this->vitalsFor($this->normalVitals()), 8));
    }

    public function test_abnormal_flags(): void
    {
        $flags = $this->vitalsFor($this->normalVitals(['temperature' => 38.2, 'systolic' => 185, 'diastolic' => 95, 'spo2' => 88, 'pulse' => 55]))->flags();

        $this->assertSame('high', $flags['temperature']);
        $this->assertSame('critical', $flags['systolic']);
        $this->assertSame('critical', $flags['spo2']);
        $this->assertSame('low', $flags['pulse']);
        $this->assertArrayNotHasKey('respiratory_rate', $flags);

        // Children: adult pulse ranges don't apply, temperature still does.
        $child = $this->vitalsFor(['pulse' => 130, 'temperature' => 38.5], 3)->flags();
        $this->assertArrayNotHasKey('pulse', $child);
        $this->assertSame('high', $child['temperature']);
    }

    public function test_vitals_outside_a_visit_and_history_page(): void
    {
        $this->actingAs($this->nurse)->post(route('vitals.store', $this->patient), $this->normalVitals())->assertRedirect(route('vitals.index', $this->patient));
        $this->actingAs($this->nurse)->post(route('vitals.store', $this->patient), $this->normalVitals(['systolic' => 150, 'diastolic' => 95]));

        // Attached to the open visit automatically.
        $this->assertSame(2, VitalSign::where('visit_id', $this->visit->id)->count());

        $this->actingAs($this->nurse)->get(route('vitals.index', $this->patient))
            ->assertOk()->assertSee('150/95')->assertSee('data-trend-chart', false);
    }

    public function test_void_rules(): void
    {
        $this->actingAs($this->nurse)->post(route('vitals.store', $this->patient), $this->normalVitals());
        $v = VitalSign::first();

        $otherNurse = User::factory()->create()->assignRole('Nurse');
        $this->actingAs($otherNurse)->patch(route('vitals.void', $v), ['void_reason' => 'x'])->assertForbidden();

        $this->actingAs($this->nurse)->patch(route('vitals.void', $v), ['void_reason' => 'Wrong patient'])->assertSessionHas('success');
        $v->refresh();
        $this->assertTrue($v->isVoided());
        $this->assertSame(0, VitalSign::valid()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'vitals_voided']);

        $this->actingAs($this->nurse)->get(route('vitals.index', $this->patient))->assertOk()->assertSee('Entered in error');
    }

    public function test_nursing_notes_are_append_only(): void
    {
        $this->actingAs($this->nurse)->post(route('nursing-notes.store', $this->patient), ['type' => 'observation', 'note' => 'Resting comfortably.'])
            ->assertSessionHas('success');

        $note = NursingNote::firstOrFail();
        $this->assertSame($this->visit->id, $note->visit_id);
        $this->assertSame($this->nurse->id, $note->user_id);

        $this->actingAs($this->nurse)->post(route('nursing-notes.store', $this->patient), ['type' => 'general', 'note' => ''])
            ->assertSessionHasErrorsIn('note', 'note');
    }

    public function test_worklist_queue_and_folder_show_vitals(): void
    {
        $this->actingAs($this->nurse)->get(route('vitals.worklist'))->assertOk()->assertSee($this->visit->queue_number)->assertSee('Take vitals');
        $this->actingAs($this->nurse)->get(route('queue.index'))->assertOk()
            ->assertSee('Take vitals')
            ->assertSee('Send to doctor (skip vitals)')
            ->assertDontSee('Return to doctor queue'); // no duplicate of the primary action
        $this->actingAs($this->nurse)->get(route('vitals.triage', $this->visit))->assertOk();

        $this->actingAs($this->nurse)->post(route('vitals.triage.store', $this->visit), $this->normalVitals(['temperature' => 39.6, 'priority' => 'normal', 'send_to_doctor' => 1]));

        $this->actingAs($this->nurse)->get(route('queue.index'))->assertOk()->assertSee('Temp 39.6');
        $this->actingAs($this->nurse)->get(route('patients.show', $this->patient))->assertOk()->assertSee('Latest vital signs')->assertSee('Critical');
        $this->actingAs($this->nurse)->get(route('vitals.create', $this->patient))->assertOk();
        // Triage page for an already-triaged visit redirects to plain recording.
        $this->actingAs($this->nurse)->get(route('vitals.triage', $this->visit))->assertRedirect(route('vitals.create', $this->patient));
    }

    public function test_permissions(): void
    {
        $records = User::factory()->create()->assignRole('Records Officer');
        $this->actingAs($records)->get(route('vitals.worklist'))->assertForbidden();
        $this->actingAs($records)->get(route('vitals.index', $this->patient))->assertForbidden();
        $this->actingAs($records)->post(route('vitals.store', $this->patient), $this->normalVitals())->assertForbidden();
    }
}
