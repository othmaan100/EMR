<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppointmentsAndQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $records;

    protected User $nurse;

    protected User $doctor;

    protected Clinic $clinic;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        Carbon::setTestNow('2026-10-05 08:00:00');

        $this->records = User::factory()->create()->assignRole('Records Officer');
        $this->nurse = User::factory()->create()->assignRole('Nurse');
        $this->doctor = User::factory()->create()->assignRole('Doctor');
        $this->clinic = Clinic::factory()->create(['code' => 'GOPD', 'requires_triage' => true]);
        $this->patient = Patient::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function book(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->records)->post(route('appointments.store'), array_merge([
            'patient_id' => $this->patient->id,
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $this->doctor->id,
            'date' => '2026-10-05',
            'time' => '10:30',
            'type' => 'new',
            'reason' => 'Headache',
        ], $overrides));
    }

    protected function walkIn(?Patient $patient = null, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->records)->post(route('visits.store', $patient ?? $this->patient), array_merge([
            'clinic_id' => $this->clinic->id,
            'visit_type' => 'outpatient',
            'priority' => 'normal',
            'complaint' => 'Fever',
        ], $overrides));
    }

    public function test_booking_an_appointment(): void
    {
        $this->book()->assertRedirect(route('appointments.index', ['date' => '2026-10-05']));

        $appt = Appointment::firstOrFail();
        $this->assertSame('2026-10-05 10:30', $appt->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('scheduled', $appt->status);
        $this->assertSame($this->records->id, $appt->booked_by);

        $this->actingAs($this->records)->get(route('appointments.index', ['date' => '2026-10-05']))
            ->assertOk()->assertSee($this->patient->hospital_number)->assertSee('10:30 AM');
    }

    public function test_cannot_book_in_the_past_or_double_book(): void
    {
        $this->book(['time' => '07:00'])->assertSessionHasErrors('time');
        $this->book(['date' => '2026-10-04'])->assertSessionHasErrors('date');

        $this->book()->assertSessionHasNoErrors();
        $other = Patient::factory()->create();
        $this->book(['patient_id' => $other->id])->assertSessionHasErrors('time'); // doctor busy
        $this->book(['time' => '11:00', 'doctor_id' => null])->assertSessionHasErrors('date'); // patient already booked there
    }

    public function test_only_doctors_can_be_assigned(): void
    {
        $this->book(['doctor_id' => $this->nurse->id])->assertSessionHasErrors('doctor_id');
    }

    public function test_reschedule_cancel_and_no_show(): void
    {
        $this->book();
        $appt = Appointment::first();

        $this->actingAs($this->records)->put(route('appointments.update', $appt), [
            'patient_id' => $this->patient->id, 'clinic_id' => $this->clinic->id, 'date' => '2026-10-07', 'time' => '09:00', 'type' => 'follow_up',
        ])->assertRedirect();
        $this->assertSame('2026-10-07 09:00', $appt->fresh()->scheduled_at->format('Y-m-d H:i'));

        $this->actingAs($this->records)->patch(route('appointments.cancel', $appt), ['cancel_reason' => 'Travelling']);
        $this->assertSame('cancelled', $appt->fresh()->status);
        $this->actingAs($this->records)->get(route('appointments.edit', $appt))->assertRedirect();

        $this->book(['patient_id' => Patient::factory()->create()->id]);
        $second = Appointment::latest('id')->first();
        $this->actingAs($this->records)->patch(route('appointments.no-show', $second));
        $this->assertSame('no_show', $second->fresh()->status);
    }

    public function test_walk_in_check_in_creates_visit_with_queue_numbers(): void
    {
        $this->walkIn()->assertRedirect(route('patients.show', $this->patient));
        $this->walkIn(Patient::factory()->create());

        $visits = Visit::orderBy('id')->get();
        $this->assertSame(['GOPD-001', 'GOPD-002'], $visits->pluck('queue_number')->all());
        $this->assertSame('V2026-000001', $visits[0]->visit_number);
        $this->assertSame(Visit::WAITING_TRIAGE, $visits[0]->status);
        $this->assertSame($this->records->id, $visits[0]->checked_in_by);
    }

    public function test_queue_numbers_restart_each_day(): void
    {
        $this->walkIn();
        Visit::first()->update(['status' => Visit::COMPLETED]);

        Carbon::setTestNow('2026-10-06 08:00:00');
        $this->walkIn();

        $this->assertSame('GOPD-001', Visit::latest('id')->first()->queue_number);
        $this->assertSame('V2026-000002', Visit::latest('id')->first()->visit_number);
    }

    public function test_patient_cannot_have_two_open_visits(): void
    {
        $this->walkIn();
        $this->walkIn()->assertSessionHasErrors('patient');
        $this->assertSame(1, Visit::count());
    }

    public function test_clinic_without_triage_sends_straight_to_doctor(): void
    {
        $direct = Clinic::factory()->create(['requires_triage' => false]);
        $this->walkIn(null, ['clinic_id' => $direct->id]);

        $this->assertSame(Visit::WAITING_DOCTOR, Visit::first()->status);
    }

    public function test_checking_in_an_appointment_links_the_visit(): void
    {
        $this->book();
        $appt = Appointment::first();

        $this->actingAs($this->records)->post(route('appointments.check-in', $appt))->assertSessionHas('success');

        $appt->refresh();
        $this->assertSame('checked_in', $appt->status);
        $this->assertSame($this->doctor->id, $appt->visit->doctor_id);
    }

    public function test_full_queue_flow(): void
    {
        $this->book();
        $appt = Appointment::first();
        $this->actingAs($this->records)->post(route('appointments.check-in', $appt));
        $visit = Visit::first();

        // Nurse sends to doctor.
        $this->actingAs($this->nurse)->patch(route('visits.move', $visit), ['status' => Visit::WAITING_DOCTOR])->assertSessionHas('success');
        $this->assertNotNull($visit->fresh()->triaged_at);

        // Nurse can't jump to completed.
        $this->actingAs($this->nurse)->patch(route('visits.move', $visit), ['status' => Visit::COMPLETED])->assertSessionHasErrors('status');

        // Doctor starts and completes.
        $this->actingAs($this->doctor)->patch(route('visits.move', $visit), ['status' => Visit::IN_CONSULTATION]);
        $this->actingAs($this->doctor)->patch(route('visits.move', $visit), ['status' => Visit::COMPLETED]);

        $visit->refresh();
        $this->assertSame(Visit::COMPLETED, $visit->status);
        $this->assertNotNull($visit->consultation_started_at);
        $this->assertNotNull($visit->completed_at);
        $this->assertSame('completed', $appt->fresh()->status);

        // Patient can now be checked in again.
        $this->walkIn()->assertSessionHasNoErrors();
    }

    public function test_unassigned_patient_is_taken_by_calling_doctor(): void
    {
        $this->walkIn(null, ['clinic_id' => Clinic::factory()->create(['requires_triage' => false])->id]);
        $visit = Visit::first();

        $this->actingAs($this->doctor)->patch(route('visits.move', $visit), ['status' => Visit::IN_CONSULTATION]);

        $this->assertSame($this->doctor->id, $visit->fresh()->doctor_id);
    }

    public function test_queue_board_orders_by_priority_and_filters_mine(): void
    {
        $normal = Patient::factory()->create(['last_name' => 'Normalperson']);
        $urgent = Patient::factory()->create(['last_name' => 'Emergencyperson']);
        $this->walkIn($normal);
        Carbon::setTestNow('2026-10-05 08:10:00');
        $this->walkIn($urgent, ['priority' => 'emergency']);

        $this->actingAs($this->nurse)->get(route('queue.index', ['clinic_id' => $this->clinic->id]))
            ->assertOk()
            ->assertSeeInOrder(['EMERGENCYPERSON', 'NORMALPERSON']);

        // Partial refresh returns just the board.
        $this->actingAs($this->nurse)->get(route('queue.index', ['partial' => 1]))
            ->assertOk()->assertDontSee('<html', false)->assertSee('EMERGENCYPERSON');

        $otherDoctor = User::factory()->create()->assignRole('Doctor');
        Visit::whereHas('patient', fn ($q) => $q->where('last_name', 'Normalperson'))->update(['doctor_id' => $otherDoctor->id]);
        $this->actingAs($this->doctor)->get(route('queue.index', ['mine' => 1]))
            ->assertOk()->assertSee('EMERGENCYPERSON')->assertDontSee('NORMALPERSON');
    }

    public function test_patient_lookup_returns_json(): void
    {
        $p = Patient::factory()->create(['first_name' => 'Kemi', 'last_name' => 'Adeyemi']);

        $this->actingAs($this->records)->getJson(route('patients.lookup', ['q' => 'adeyemi']))
            ->assertOk()->assertJsonFragment(['id' => $p->id, 'hospital_number' => $p->hospital_number]);
        $this->actingAs($this->records)->getJson(route('patients.lookup', ['q' => 'a']))->assertExactJson([]);
    }

    public function test_permissions(): void
    {
        $cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($cashier)->get(route('queue.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('appointments.index'))->assertForbidden();

        $this->walkIn();
        $this->actingAs($this->records)->patch(route('visits.move', Visit::first()), ['status' => Visit::WAITING_DOCTOR])->assertForbidden();
    }

    public function test_pages_render(): void
    {
        $admin = $this->superAdmin();
        $this->book();
        $this->walkIn(Patient::factory()->create());

        foreach ([
            route('appointments.index'),
            route('appointments.create', ['patient_id' => $this->patient->id]),
            route('appointments.edit', Appointment::first()),
            route('queue.index'),
            route('visits.create', $this->patient),
            route('patients.show', $this->patient),
            route('admin.clinics.index'),
            route('admin.clinics.create'),
            route('admin.clinics.edit', $this->clinic),
            route('dashboard'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_clinic_admin(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)->post(route('admin.clinics.store'), [
            'name' => 'Paediatrics', 'code' => 'paed', 'requires_triage' => 1, 'is_active' => 1,
        ])->assertRedirect(route('admin.clinics.index'));
        $this->assertDatabaseHas('clinics', ['code' => 'PAED']);

        $this->walkIn();
        $this->actingAs($admin)->delete(route('admin.clinics.destroy', $this->clinic))->assertSessionHas('error');
    }
}
