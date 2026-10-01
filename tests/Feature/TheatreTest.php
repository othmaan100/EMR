<?php

namespace Tests\Feature;

use App\Models\BillItem;
use App\Models\Patient;
use App\Models\Pregnancy;
use App\Models\Price;
use App\Models\Service;
use App\Models\Surgery;
use App\Models\SurgicalProcedure;
use App\Models\Theatre;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TheatreTest extends TestCase
{
    use RefreshDatabase;

    protected User $surgeon;

    protected User $nurse;

    protected User $anaesthetist;

    protected Patient $patient;

    protected Theatre $theatre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->seed(CatalogSeeder::class);
        Carbon::setTestNow('2026-10-12 07:00:00');

        $this->surgeon = User::factory()->create(['name' => 'Dr Surgeon'])->assignRole('Doctor');
        $this->nurse = User::factory()->create()->assignRole('Nurse');
        $this->anaesthetist = User::factory()->create()->assignRole('Anaesthetist');
        $this->patient = Patient::factory()->create();
        $this->theatre = Theatre::firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function book(array $overrides = [], ?Patient $patient = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->surgeon)->post(route('theatre.store', $patient ?? $this->patient), array_merge([
            'surgical_procedure_id' => SurgicalProcedure::where('code', 'HERNIA')->value('id'),
            'theatre_id' => $this->theatre->id, 'surgeon_id' => $this->surgeon->id, 'anaesthetist_id' => $this->anaesthetist->id,
            'urgency' => 'elective', 'scheduled_at' => '2026-10-12T09:00', 'estimated_minutes' => 60, 'indication' => 'Right inguinal hernia',
        ], $overrides));
    }

    protected function allItems(string $phase): array
    {
        return array_keys(Surgery::CHECKLIST[$phase]['items']);
    }

    public function test_booking_and_clash_detection(): void
    {
        $this->book()->assertSessionHasNoErrors();
        $s = Surgery::sole();
        $this->assertSame('Inguinal herniorrhaphy', $s->procedure_name);
        $this->assertMatchesRegularExpression('/^SUR2026-000001$/', $s->surgery_number);

        // Same theatre, overlapping.
        $this->book(['scheduled_at' => '2026-10-12T09:30'], Patient::factory()->create())->assertSessionHasErrors('scheduled_at');
        // Same surgeon in another theatre, overlapping.
        $t2 = Theatre::create(['code' => 'MT2', 'name' => 'Theatre 2']);
        $this->book(['scheduled_at' => '2026-10-12T09:45', 'theatre_id' => $t2->id], Patient::factory()->create())->assertSessionHasErrors('scheduled_at');
        // Back-to-back is fine.
        $this->book(['scheduled_at' => '2026-10-12T10:00'], Patient::factory()->create())->assertSessionHasNoErrors();

        // Elective in the past rejected; emergencies may be recorded retrospectively.
        $this->book(['scheduled_at' => '2026-10-11T09:00'], Patient::factory()->create())->assertSessionHasErrors('scheduled_at');
        $this->book(['scheduled_at' => '2026-10-12T06:00', 'urgency' => 'emergency', 'theatre_id' => $t2->id, 'surgeon_id' => null],
            Patient::factory()->create())->assertSessionHasNoErrors();

        $this->actingAs($this->nurse)->get(route('theatre.index', ['date' => '2026-10-12']))->assertOk()->assertSee('Inguinal herniorrhaphy')->assertSee('Emergency');
    }

    public function test_full_theatre_pathway_with_who_checklist_and_charges(): void
    {
        $proc = SurgicalProcedure::where('code', 'HERNIA')->first();
        Price::create(['billable_type' => $proc->getMorphClass(), 'billable_id' => $proc->id, 'amount' => 150000]);
        $fee = Service::where('code', Service::THEATRE_FEE)->first();
        Price::create(['billable_type' => $fee->getMorphClass(), 'billable_id' => $fee->id, 'amount' => 20000]);

        $this->book();
        $s = Surgery::sole();

        // Sign in is locked until pre-op is complete.
        $this->actingAs($this->nurse)->post(route('theatre.checklist', [$s, 'sign_in']), ['items' => $this->allItems('sign_in')])
            ->assertSessionHasErrors('checklist');

        $this->actingAs($this->nurse)->post(route('theatre.preop', $s), ['consent_signed' => 1, 'fasting_confirmed' => 0, 'asa_grade' => 2])
            ->assertSessionHas('warning');
        $this->assertSame('scheduled', $s->fresh()->status);
        $this->actingAs($this->nurse)->post(route('theatre.preop', $s), ['consent_signed' => 1, 'fasting_confirmed' => 1, 'site_marked' => 1, 'asa_grade' => 2]);
        $this->assertSame('ready', $s->fresh()->status);

        // Phases in order, every item required.
        $this->actingAs($this->nurse)->post(route('theatre.checklist', [$s, 'time_out']), ['items' => $this->allItems('time_out')])
            ->assertSessionHasErrors('checklist');
        $this->actingAs($this->nurse)->post(route('theatre.checklist', [$s, 'sign_in']), ['items' => [0, 1, 2]])
            ->assertSessionHasErrors('checklist');

        Carbon::setTestNow('2026-10-12 09:05:00');
        $this->actingAs($this->anaesthetist)->post(route('theatre.checklist', [$s, 'sign_in']), ['items' => $this->allItems('sign_in')])->assertSessionHasNoErrors();
        $s->refresh();
        $this->assertSame('in_theatre', $s->status);
        $this->assertSame($this->anaesthetist->name, $s->checklist['sign_in']['by_name']);

        $this->actingAs($this->anaesthetist)->post(route('theatre.anaesthesia', $s), ['anaesthesia_type' => 'Spinal', 'anaesthesia_drugs' => 'Bupivacaine 0.5% heavy 3 ml']);
        $this->actingAs($this->anaesthetist)->post(route('theatre.observe', $s), ['pulse' => 78, 'systolic' => 85, 'diastolic' => 50, 'spo2' => 98])
            ->assertSessionHas('warning'); // hypotension flagged

        Carbon::setTestNow('2026-10-12 09:15:00');
        $this->actingAs($this->nurse)->post(route('theatre.checklist', [$s, 'time_out']), ['items' => $this->allItems('time_out')]);

        // Surgeon can't sign before Sign out.
        $note = ['findings' => 'Indirect sac', 'procedure_performed' => 'Herniotomy + Lichtenstein mesh repair', 'postop_orders' => 'Analgesia, mobilise day 0', 'blood_loss_ml' => 50];
        $this->actingAs($this->surgeon)->post(route('theatre.complete', $s), $note)->assertSessionHasErrors('checklist');

        Carbon::setTestNow('2026-10-12 10:05:00');
        $this->actingAs($this->nurse)->post(route('theatre.checklist', [$s, 'sign_out']), ['items' => $this->allItems('sign_out')]);
        $this->actingAs($this->nurse)->post(route('theatre.complete', $s), $note)->assertForbidden(); // only surgeons sign notes

        $this->actingAs($this->surgeon)->post(route('theatre.complete', $s), $note)->assertSessionHas('success');
        $s->refresh();
        $this->assertSame('completed', $s->status);
        $this->assertSame(60, $s->durationMinutes());

        $this->assertSame([150000.0, 20000.0], BillItem::where('source_type', $s->getMorphClass())->orderBy('id')->pluck('amount')->map(fn ($v) => (float) $v)->all());

        $this->actingAs($this->surgeon)->get(route('theatre.note', $s))->assertOk()->assertSee('Lichtenstein')->assertSee('Sign out: ✔', false);
        $this->actingAs($this->surgeon)->get(route('patients.show', $this->patient))->assertOk()->assertSee('Operations');
    }

    public function test_postpone_reschedule_and_cancel(): void
    {
        $this->book();
        $s = Surgery::sole();

        $this->actingAs($this->surgeon)->post(route('theatre.stop', $s), ['status' => 'postponed', 'reason' => 'Patient not fasted'])->assertSessionHas('success');
        $this->assertSame('postponed', $s->fresh()->status);

        $this->actingAs($this->surgeon)->put(route('theatre.update', $s), [
            'surgical_procedure_id' => $s->surgical_procedure_id, 'theatre_id' => $this->theatre->id, 'urgency' => 'elective',
            'scheduled_at' => '2026-10-13T09:00', 'estimated_minutes' => 60, 'indication' => 'Right inguinal hernia',
        ])->assertSessionHasNoErrors();
        $s->refresh();
        $this->assertSame('scheduled', $s->status);
        $this->assertSame('2026-10-13 09:00', $s->scheduled_at->format('Y-m-d H:i'));

        $this->actingAs($this->surgeon)->post(route('theatre.stop', $s), ['status' => 'cancelled', 'reason' => 'Patient declined']);
        $this->assertSame('cancelled', $s->fresh()->status);
        $this->actingAs($this->nurse)->post(route('theatre.preop', $s), ['consent_signed' => 1])->assertSessionHasErrors('status');
    }

    public function test_caesarean_booking_from_pregnancy(): void
    {
        $mother = Patient::factory()->create(['gender' => 'female']);
        $p = new Pregnancy(['lmp' => '2026-01-10', 'edd' => '2026-10-17', 'gravida' => 2, 'parity' => 1]);
        $p->patient_id = $mother->id;
        $p->save();

        $this->actingAs($this->surgeon)->get(route('maternity.show', $p))->assertOk()->assertSee('Book caesarean section');
        $this->actingAs($this->surgeon)->get(route('theatre.create', ['patient' => $mother, 'pregnancy' => $p->id]))
            ->assertOk()->assertSee('G2P1');

        $this->book(['surgical_procedure_id' => SurgicalProcedure::where('code', 'CS')->value('id'), 'pregnancy_id' => $p->id, 'urgency' => 'urgent'], $mother)
            ->assertSessionHasNoErrors();
        $this->assertSame($p->id, Surgery::sole()->pregnancy_id);
    }

    public function test_permissions_catalogues_and_report(): void
    {
        $this->book();
        $s = Surgery::sole();

        $cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($cashier)->get(route('theatre.index'))->assertForbidden();
        $this->actingAs($this->anaesthetist)->get(route('theatre.create', $this->patient))->assertForbidden(); // anaesthetists don't book
        $this->actingAs($this->anaesthetist)->get(route('theatre.show', $s))->assertOk();

        $admin = User::factory()->create()->assignRole('Administrator');
        foreach (['procedures', 'theatres'] as $catalog) {
            $this->actingAs($admin)->get(route('admin.catalogs.index', $catalog))->assertOk();
        }
        $this->actingAs($admin)->get(route('billing.prices', ['type' => 'procedures']))->assertOk()->assertSee('Caesarean section');
        $this->actingAs($admin)->get(route('reports.show', ['report' => 'theatre', 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertSee('Operations completed');
        $this->actingAs($admin)->get(route('theatre.edit', $s))->assertOk();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Operations today');
    }
}
