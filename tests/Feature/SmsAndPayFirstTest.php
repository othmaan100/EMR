<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BillItem;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Price;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\Vaccine;
use App\Models\Visit;
use App\Services\QueueService;
use App\Services\SmsNotifier;
use App\Services\SmsService;
use App\Support\Settings;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsAndPayFirstTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    // ------------------------------------------------------------------ SMS

    public function test_phone_numbers_are_normalised(): void
    {
        $sms = app(SmsService::class);

        $this->assertSame('2348031234567', $sms->normalise('0803 123 4567'));
        $this->assertSame('2348031234567', $sms->normalise('+234 803-123-4567'));
        $this->assertSame('447700900123', $sms->normalise('00447700900123'));
        $this->assertNull($sms->normalise('12'));
        $this->assertNull($sms->normalise(null));
    }

    public function test_log_only_mode_records_without_sending(): void
    {
        Http::fake();
        $patient = Patient::factory()->create(['phone' => '08031234567']);

        $message = app(SmsService::class)->queue($patient, null, 'Hello', 'test');
        $this->assertSame('queued', $message->status);

        $this->artisan('emr:send-sms')->assertSuccessful();

        $this->assertSame('logged', $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_termii_delivery_and_failure_retries(): void
    {
        app(Settings::class)->set(['sms_provider' => 'termii', 'sms_sender_id' => 'MYHOSP',
            'sms_api_key' => \Illuminate\Support\Facades\Crypt::encryptString('secret-key')]);

        Http::fakeSequence()
            ->push(['code' => 'ok', 'message_id' => 'T-1', 'message' => 'Successfully Sent'])
            ->push(['message' => 'Insufficient balance'], 400)
            ->push(['message' => 'Insufficient balance'], 400)
            ->push(['message' => 'Insufficient balance'], 400);

        $sms = app(SmsService::class);
        $ok = $sms->deliver($sms->queue(null, '08030000001', 'One', 'test'));
        $this->assertSame('sent', $ok->status);
        $this->assertSame('T-1', $ok->provider_message_id);
        Http::assertSent(fn ($r) => $r['api_key'] === 'secret-key' && $r['to'] === '2348030000001' && $r['from'] === 'MYHOSP');

        $bad = $sms->queue(null, '08030000002', 'Two', 'test');
        $sms->deliver($bad);
        $this->assertSame('queued', $bad->fresh()->status); // will retry
        $sms->deliver($bad);
        $sms->deliver($bad);
        $this->assertSame('failed', $bad->fresh()->status);
        $this->assertStringContainsString('Insufficient balance', $bad->fresh()->error);
    }

    public function test_twilio_and_africas_talking_requests(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
            'api.sandbox.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => [['status' => 'Success', 'messageId' => 'AT-9']]]], 201),
        ]);
        $settings = app(Settings::class);
        $sms = app(SmsService::class);
        $key = \Illuminate\Support\Facades\Crypt::encryptString('tok');

        $settings->set(['sms_provider' => 'twilio', 'sms_username' => 'AC1', 'sms_sender_id' => '+15550001', 'sms_api_key' => $key]);
        $this->assertSame('SM123', $sms->deliver($sms->queue(null, '08030000001', 'Hi', 'test'))->provider_message_id);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'Accounts/AC1/Messages.json') && $r['To'] === '+2348030000001');

        $settings->set(['sms_provider' => 'africastalking', 'sms_username' => 'sandbox']);
        $this->assertSame('AT-9', $sms->deliver($sms->queue(null, '08030000001', 'Hi', 'test'))->provider_message_id);
        Http::assertSent(fn ($r) => $r->hasHeader('apiKey', 'tok') && $r['username'] === 'sandbox');
    }

    public function test_appointment_reminders_are_sent_once(): void
    {
        app(Settings::class)->set(['sms_appointment_reminders' => '1', 'hospital_name' => 'Hope Clinic']);
        $patient = Patient::factory()->create(['first_name' => 'Amina', 'phone' => '08031112222']);
        $clinic = Clinic::factory()->create(['name' => 'Eye Clinic']);
        Appointment::forceCreate(['patient_id' => $patient->id, 'clinic_id' => $clinic->id, 'scheduled_at' => today()->addDay()->setTime(9, 30), 'status' => 'scheduled']);
        Appointment::forceCreate(['patient_id' => $patient->id, 'clinic_id' => $clinic->id, 'scheduled_at' => today()->addDays(2)->setTime(9, 30), 'status' => 'scheduled']);

        $this->artisan('emr:sms-reminders')->assertSuccessful();
        $this->artisan('emr:sms-reminders')->assertSuccessful(); // re-run must not duplicate

        $messages = SmsMessage::where('type', 'appointment_reminder')->get();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Dear Amina', $messages[0]->body);
        $this->assertStringContainsString('Eye Clinic', $messages[0]->body);
        $this->assertStringContainsString('09:30 AM', $messages[0]->body);
        $this->assertStringContainsString('Hope Clinic', $messages[0]->body);
    }

    public function test_reminders_respect_switches(): void
    {
        $patient = Patient::factory()->create(['phone' => '08031112222']);
        Appointment::forceCreate(['patient_id' => $patient->id, 'clinic_id' => Clinic::factory()->create()->id,
            'scheduled_at' => today()->addDay()->setTime(9, 0), 'status' => 'scheduled']);
        app(SmsNotifier::class)->resultsReady($patient, 'laboratory', 'LAB-1');

        $this->artisan('emr:sms-reminders')->assertSuccessful();
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_results_ready_message_is_generic(): void
    {
        app(Settings::class)->set('sms_results_ready', '1');
        $patient = Patient::factory()->create(['phone' => '08031112222']);

        app(SmsNotifier::class)->resultsReady($patient, 'laboratory', 'LAB2026-000007');
        app(SmsNotifier::class)->resultsReady($patient, 'laboratory', 'LAB2026-000007');

        $this->assertSame(1, SmsMessage::count());
        $this->assertStringContainsString('laboratory results are ready', SmsMessage::first()->body);
    }

    public function test_immunization_reminder_goes_to_carer_three_days_ahead(): void
    {
        $this->seed(CatalogSeeder::class);
        app(Settings::class)->set('sms_immunization_reminders', '1');
        $vaccine = Vaccine::active()->where('age_days', 42)->firstOrFail(); // 6-week doses
        $mother = Patient::factory()->create();
        $baby = Patient::factory()->create(['first_name' => 'Tobi', 'mother_id' => $mother->id, 'phone' => null, 'nok_phone' => '08035556666',
            'date_of_birth' => today()->addDays(3)->subDays(42)]);

        $this->artisan('emr:sms-reminders')->assertSuccessful();

        $sms = SmsMessage::where('type', 'immunization_reminder')->where('patient_id', $baby->id)->get();
        $this->assertNotEmpty($sms);
        $this->assertSame('2348035556666', $sms[0]->phone);
        $this->assertStringContainsString('Tobi', $sms[0]->body);
        $this->assertTrue($sms->contains(fn ($m) => str_contains($m->body, $vaccine->label)));
    }

    public function test_sms_settings_encrypt_key_and_page_sends_test(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)->post(route('admin.settings.update', 'sms'), [
            'sms_provider' => 'none', 'sms_country_code' => '234', 'sms_api_key' => 'my-secret',
            'sms_results_ready' => '1', 'sms_tpl_results' => 'Results for {name} ready.',
        ])->assertSessionHasNoErrors();

        $stored = setting('sms_api_key');
        $this->assertNotSame('my-secret', $stored);
        $this->assertSame('my-secret', SmsService::secret('sms_api_key'));

        // Blank key keeps the saved one.
        $this->actingAs($admin)->post(route('admin.settings.update', 'sms'), ['sms_provider' => 'none', 'sms_country_code' => '234', 'sms_api_key' => '']);
        $this->assertSame('my-secret', SmsService::secret('sms_api_key'));

        $this->actingAs($admin)->get(route('admin.settings.edit', ['section' => 'sms']))->assertOk()->assertDontSee('my-secret');
        $this->actingAs($admin)->post(route('admin.sms.test'), ['phone' => '0803 000 0000'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('admin.sms.index'))->assertOk()->assertSee('2348030000000');

        $cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($cashier)->get(route('admin.sms.index'))->assertForbidden();
    }

    // -------------------------------------------------------- pay-first

    protected function prescriptionFor(Patient $patient, User $doctor, Drug $drug, int $qty): Prescription
    {
        $clinic = Clinic::factory()->create(['requires_triage' => false]);
        $visit = app(QueueService::class)->checkIn($patient, ['clinic_id' => $clinic->id], $doctor);
        $this->actingAs($doctor)->patch(route('visits.move', $visit), ['status' => Visit::IN_CONSULTATION]);
        $this->actingAs($doctor)->get(route('consultations.show', $visit));
        $this->actingAs($doctor)->post(route('consultations.prescribe', Consultation::where('visit_id', $visit->id)->firstOrFail()), [
            'drug' => $drug->label, 'dose' => '1', 'route' => 'Oral', 'frequency' => 'TDS',
            'duration_value' => 5, 'duration_unit' => 'days', 'quantity' => $qty,
        ])->assertSessionHasNoErrors();

        return Prescription::latest('id')->firstOrFail();
    }

    public function test_pay_first_flow(): void
    {
        $this->seed(CatalogSeeder::class);
        app(Settings::class)->set('pharmacy_pay_first', '1');
        $doctor = User::factory()->create()->assignRole('Doctor');
        $pharmacist = User::factory()->create()->assignRole('Pharmacist');
        $cashier = User::factory()->create()->assignRole('Cashier');
        $patient = Patient::factory()->create(['allergies' => null, 'payment_type' => 'self']);
        $drug = Drug::where('name', 'Paracetamol')->where('form', 'Tablet')->firstOrFail();
        Price::create(['billable_type' => $drug->getMorphClass(), 'billable_id' => $drug->id, 'amount' => 20]);
        $this->actingAs($pharmacist)->post(route('inventory.receive.store'), [
            'received_on' => today()->toDateString(),
            'lines' => [['drug_id' => $drug->id, 'batch_number' => 'P1', 'expiry_date' => now()->addYear()->toDateString(), 'quantity' => 100]],
        ])->assertSessionHasNoErrors();

        $rx = $this->prescriptionFor($patient, $doctor, $drug, 30);
        $item = $rx->items()->firstOrFail();

        // Dispensing before pricing does nothing.
        $this->actingAs($pharmacist)->post(route('pharmacy.dispense', $rx), ['lines' => [$item->id => ['quantity' => 30]]]);
        $this->assertSame(0, $item->fresh()->quantity_dispensed);

        // Price: charge created, stock untouched.
        $this->actingAs($pharmacist)->post(route('pharmacy.price', $rx), ['lines' => [$item->id => ['quantity' => 30]]])->assertSessionHasNoErrors();
        $charge = BillItem::whereMorphedTo('source', $item)->sole();
        $this->assertEquals(600, $charge->amount);
        $this->assertSame(100, $drug->usableStock());
        $this->actingAs($pharmacist)->get(route('pharmacy.show', $rx))->assertOk()->assertSee('awaiting payment');

        // Pricing again does not double charge.
        $this->actingAs($pharmacist)->post(route('pharmacy.price', $rx), ['lines' => [$item->id => ['quantity' => 30]]]);
        $this->assertSame(1, BillItem::whereMorphedTo('source', $item)->count());

        // Unpaid → blocked.
        $this->actingAs($pharmacist)->post(route('pharmacy.dispense', $rx), ['lines' => [$item->id => ['quantity' => 30]]])
            ->assertSessionHasErrors("lines.{$item->id}.quantity");

        // Pay, then dispense without a second charge.
        $this->actingAs($cashier)->post(route('billing.pay', $patient), ['items' => [$charge->id], 'amount' => 600, 'method' => 'cash'])->assertSessionHasNoErrors();
        $this->actingAs($pharmacist)->post(route('pharmacy.dispense', $rx), ['lines' => [$item->id => ['quantity' => 40]]])
            ->assertSessionHasErrors("lines.{$item->id}.quantity"); // more than paid for
        $this->actingAs($pharmacist)->post(route('pharmacy.dispense', $rx), ['lines' => [$item->id => ['quantity' => 30]]])->assertSessionHasNoErrors();

        $this->assertSame(30, $item->fresh()->quantity_dispensed);
        $this->assertSame(70, $drug->usableStock());
        $this->assertSame(1, BillItem::whereMorphedTo('source', $item)->count());
        $this->assertSame('dispensed', $rx->fresh()->status);
    }

    public function test_marking_priced_item_unavailable_cancels_unpaid_charge(): void
    {
        $this->seed(CatalogSeeder::class);
        app(Settings::class)->set('pharmacy_pay_first', '1');
        $doctor = User::factory()->create()->assignRole('Doctor');
        $pharmacist = User::factory()->create()->assignRole('Pharmacist');
        $patient = Patient::factory()->create(['allergies' => null, 'payment_type' => 'self']);
        $drug = Drug::where('name', 'Paracetamol')->where('form', 'Tablet')->firstOrFail();
        Price::create(['billable_type' => $drug->getMorphClass(), 'billable_id' => $drug->id, 'amount' => 20]);
        $this->actingAs($pharmacist)->post(route('inventory.receive.store'), [
            'received_on' => today()->toDateString(),
            'lines' => [['drug_id' => $drug->id, 'batch_number' => 'P1', 'expiry_date' => now()->addYear()->toDateString(), 'quantity' => 100]],
        ]);
        $rx = $this->prescriptionFor($patient, $doctor, $drug, 10);
        $item = $rx->items()->firstOrFail();

        $this->actingAs($pharmacist)->post(route('pharmacy.price', $rx), ['lines' => [$item->id => ['quantity' => 10]]]);
        $this->actingAs($pharmacist)->post(route('pharmacy.price', $rx), ['lines' => [$item->id => ['unavailable' => 'Patient declined']]])->assertSessionHasNoErrors();

        $this->assertNotNull(BillItem::whereMorphedTo('source', $item)->sole()->voided_at);
        $this->assertSame(0, $item->fresh()->billed_quantity);
        $this->assertSame('Patient declined', $item->fresh()->not_dispensed_reason);
    }
}
