<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Clinic;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\PortalService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientPortalTest extends TestCase
{
    use RefreshDatabase;

    protected User $records;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        app(Settings::class)->set('portal_enabled', '1');

        $this->records = User::factory()->create()->assignRole('Records Officer');
        $this->patient = Patient::factory()->create(['first_name' => 'Aisha', 'phone' => '08031234567']);
    }

    /**
     * Issue a code and activate it; returns the signed-in account.
     */
    protected function activated(Patient $patient, string $password = 'secret123'): PatientAccount
    {
        $code = app(PortalService::class)->issueAccess($patient, $this->records);
        app(PortalService::class)->activate($patient->hospital_number, $code, $password);

        return PatientAccount::where('patient_id', $patient->id)->sole();
    }

    public function test_staff_issue_code_patient_activates_and_signs_in(): void
    {
        $this->actingAs($this->records, 'web')->get(route('patients.show', $this->patient))->assertOk()->assertSee('Give portal access');

        $response = $this->actingAs($this->records, 'web')->post(route('patients.portal.store', $this->patient), ['send_sms' => '1']);
        $response->assertRedirect(route('patients.portal.letter', $this->patient));
        $code = session('portal_code');
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code);

        $this->actingAs($this->records, 'web')->get(route('patients.portal.letter', $this->patient))->assertOk()->assertSee($code);

        // SMS was queued; once delivered, the code is blanked in the log.
        $sms = SmsMessage::where('type', 'portal_access')->sole();
        $this->artisan('emr:send-sms');
        $this->assertStringNotContainsString($code, $sms->fresh()->body);

        // Staff session ends; patient activates in a fresh browser.
        auth()->guard('web')->logout();
        $this->post(route('portal.activate'), ['hospital_number' => $this->patient->hospital_number, 'code' => 'WRON-GCOD',
            'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertSessionHasErrors('code');

        $this->post(route('portal.activate'), ['hospital_number' => strtolower($this->patient->hospital_number), 'code' => strtolower($code),
            'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticated('patient');
        $this->assertGuest('web');

        $this->get(route('portal.dashboard'))->assertOk()->assertSee('Aisha');
        $this->post(route('portal.logout'));
        $this->assertGuest('patient');

        // Code is single-use; password works.
        $this->post(route('portal.activate'), ['hospital_number' => $this->patient->hospital_number, 'code' => $code,
            'password' => 'other1234', 'password_confirmation' => 'other1234'])->assertSessionHasErrors('code');
        $this->post(route('portal.login'), ['hospital_number' => $this->patient->hospital_number, 'password' => 'secret123'])
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_patients_cannot_reach_staff_pages_or_other_patients(): void
    {
        $account = $this->activated($this->patient);
        $other = Patient::factory()->create();
        $otherBill = new Bill;
        $otherBill->forceFill(['bill_number' => 'BILL-X', 'patient_id' => $other->id, 'claim_status' => 'none'])->save();

        $this->actingAs($account, 'patient');
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('portal.bills.show', $otherBill))->assertNotFound();
        $this->post(route('portal.switch', $other))->assertNotFound();

        // Staff cannot use the portal as a patient.
        auth()->guard('patient')->logout();
        $this->actingAs($this->records, 'web')->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
    }

    public function test_only_released_results_are_visible(): void
    {
        $account = $this->activated($this->patient);
        $pending = LabOrder::forceCreate(['order_number' => 'LAB-1', 'patient_id' => $this->patient->id, 'status' => 'in_progress']);
        $released = LabOrder::forceCreate(['order_number' => 'LAB-2', 'patient_id' => $this->patient->id, 'status' => 'completed', 'completed_at' => now()]);

        $this->actingAs($account, 'patient');
        $this->get(route('portal.results'))->assertOk()->assertSee('LAB-2')->assertDontSee('LAB-1');
        $this->get(route('portal.results.lab', $released))->assertOk();
        $this->get(route('portal.results.lab', $pending))->assertNotFound();
    }

    public function test_appointment_request_confirm_and_cancel(): void
    {
        $account = $this->activated($this->patient);
        $clinic = Clinic::factory()->create(['name' => 'Diabetes Clinic']);
        $date = today()->addDays(5)->toDateString();

        $this->actingAs($account, 'patient')->post(route('portal.appointments.request'), [
            'clinic_id' => $clinic->id, 'date' => $date, 'time_of_day' => 'afternoon', 'reason' => 'Sugar check',
        ])->assertSessionHasNoErrors();

        $appt = Appointment::sole();
        $this->assertSame('requested', $appt->status);
        $this->assertSame('portal', $appt->source);
        $this->get(route('portal.appointments'))->assertSee('Waiting for the hospital to confirm');

        auth()->guard('patient')->logout();
        app(Settings::class)->set('sms_appointment_reminders', '1');
        $this->actingAs($this->records, 'web')->get(route('appointments.requests'))->assertOk()->assertSee('Sugar check');
        $this->actingAs($this->records, 'web')->patch(route('appointments.confirm', $appt), ['date' => $date, 'time' => '10:30'])->assertSessionHasNoErrors();
        $appt->refresh();
        $this->assertSame('scheduled', $appt->status);
        $this->assertSame('10:30', $appt->scheduled_at->format('H:i'));
        $this->assertSame(1, SmsMessage::where('type', 'appointment_confirmed')->count());

        auth()->guard('web')->logout();
        $this->actingAs($account, 'patient')->patch(route('portal.appointments.cancel', $appt))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $appt->fresh()->status);
    }

    public function test_mother_can_view_child_record(): void
    {
        $account = $this->activated($this->patient);
        $baby = Patient::factory()->create(['first_name' => 'Baby', 'mother_id' => $this->patient->id, 'date_of_birth' => today()->subWeeks(7)]);

        $this->actingAs($account, 'patient');
        $this->post(route('portal.switch', $baby))->assertRedirect(route('portal.dashboard'));
        $this->get(route('portal.immunizations'))->assertOk()->assertSee('Immunization card');
        $this->get(route('portal.dashboard'))->assertSee($baby->hospital_number);
    }

    public function test_lockout_disable_and_portal_switch(): void
    {
        $this->activated($this->patient);

        foreach (range(1, 5) as $i) {
            $this->post(route('portal.login'), ['hospital_number' => $this->patient->hospital_number, 'password' => 'wrong-pass']);
        }
        $this->post(route('portal.login'), ['hospital_number' => $this->patient->hospital_number, 'password' => 'secret123'])
            ->assertSessionHasErrors('hospital_number');
        $this->assertGuest('patient');

        // Staff switch the account off.
        $account = PatientAccount::sole();
        $account->forceFill(['locked_until' => null])->save();
        $this->actingAs($this->records, 'web')->delete(route('patients.portal.destroy', $this->patient))->assertSessionHasNoErrors();
        auth()->guard('web')->logout();
        $this->post(route('portal.login'), ['hospital_number' => $this->patient->hospital_number, 'password' => 'secret123'])
            ->assertSessionHasErrors('hospital_number');

        // Portal switched off in settings → 404.
        app(Settings::class)->set('portal_enabled', '0');
        $this->get(route('portal.login'))->assertNotFound();
    }

    public function test_idle_session_is_signed_out_and_permissions(): void
    {
        $account = $this->activated($this->patient);
        $this->actingAs($account, 'patient')->withSession(['portal_last_activity_at' => time() - 3600])
            ->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
        $this->assertGuest('patient');

        $doctor = User::factory()->create()->assignRole('Doctor');
        $this->actingAs($doctor, 'web')->post(route('patients.portal.store', $this->patient))->assertForbidden();
    }
}
