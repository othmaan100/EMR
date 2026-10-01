<?php

namespace Tests\Feature;

use App\Integrations\Lab\Hl7Message;
use App\Models\BillItem;
use App\Models\ClaimBatch;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\ImagingOrder;
use App\Models\ImagingTest;
use App\Models\InsuranceProvider;
use App\Models\IntegrationMessage;
use App\Models\LabAnalyzer;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\OnlinePayment;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Payment;
use App\Models\Price;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\ClaimService;
use App\Services\PortalService;
use App\Services\QueueService;
use App\Support\Settings;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $doctor;

    protected Patient $patient;

    protected Visit $visit;

    protected Consultation $consultation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->seed(CatalogSeeder::class);
        Http::preventStrayRequests();

        $this->admin = User::factory()->create()->assignRole('Administrator');
        $this->doctor = User::factory()->create()->assignRole('Doctor');
        $this->patient = Patient::factory()->create(['email' => 'amina@example.com', 'phone' => '08031234567']);

        $clinic = Clinic::factory()->create(['requires_triage' => false]);
        $this->visit = app(QueueService::class)->checkIn($this->patient, ['clinic_id' => $clinic->id], $this->doctor);
        $this->actingAs($this->doctor)->patch(route('visits.move', $this->visit), ['status' => Visit::IN_CONSULTATION]);
        $this->actingAs($this->doctor)->get(route('consultations.show', $this->visit));
        $this->consultation = Consultation::firstOrFail();
    }

    protected function secrets(array $values): void
    {
        app(Settings::class)->set(collect($values)->map(fn ($v, $k) => in_array($k, \App\Support\HospitalProfile::SECRETS, true) ? Crypt::encryptString($v) : $v)->all());
    }

    // ------------------------------------------------------------------ lab analysers

    protected function collectedFbcOrder(): LabOrder
    {
        $this->actingAs($this->doctor)->post(route('consultations.lab.store', $this->consultation), [
            'tests' => LabTest::whereIn('code', ['FBC', 'MRDT'])->pluck('id')->all(), 'priority' => 'routine',
        ]);
        $order = LabOrder::with('items.test.parameters')->latest('id')->firstOrFail();
        $scientist = User::factory()->create()->assignRole('Lab Scientist');
        $this->actingAs($scientist)->post(route('lab.collect', $order))->assertSessionHasNoErrors();

        return $order->fresh(['items.test.parameters']);
    }

    public function test_lab_analyzer_hl7_results_are_mapped_and_await_verification(): void
    {
        $order = $this->collectedFbcOrder();
        $fbc = LabTest::where('code', 'FBC')->with('parameters')->sole();
        $param = fn (string $name) => $fbc->parameters->firstWhere('name', $name)->id;

        // Admin adds the analyser (token shown once) and maps its codes.
        $this->actingAs($this->admin)->post(route('admin.integrations.analyzers.store'), ['name' => 'Sysmex XN', 'code' => 'sysmex1'])->assertSessionHasNoErrors();
        $analyzer = LabAnalyzer::sole();
        $token = session('analyzer_token');
        $this->assertStringStartsWith('lab_', $token);
        $this->assertNotSame($token, $analyzer->token_hash);
        $this->assertFalse((bool) $analyzer->user->is_active, 'The analyser account must never be able to sign in');

        $this->actingAs($this->admin)->post(route('admin.integrations.analyzers.mappings', $analyzer), ['map' => [
            ['analyzer_code' => 'HGB', 'target' => "{$fbc->id}:{$param('Haemoglobin')}"],
            ['analyzer_code' => 'WBC', 'target' => "{$fbc->id}:{$param('WBC')}"],
        ]])->assertSessionHasNoErrors();

        $hl7 = "\x0bMSH|^~\\&|XN|LAB|EMR|HOSP|20260930120000||ORU^R01|MSG0001|P|2.5\r"
            ."PID|1||{$this->patient->hospital_number}\r"
            ."OBR|1|{$order->order_number}||FBC\r"
            ."OBX|1|NM|HGB^Haemoglobin||9.1|g/dL|12-17|L|||F\r"
            ."OBX|2|NM|WBC^White cells||6.4|10*9/L|4-11|N|||F\r"
            ."OBX|3|NM|RDW^RDW||14|%||||F\r\x1c\r";

        $response = $this->call('POST', '/api/v1/lab/results', [], [], [], ['HTTP_AUTHORIZATION' => "Bearer {$token}", 'CONTENT_TYPE' => 'application/hl7-v2'], $hl7);
        $response->assertOk();
        $this->assertStringContainsString('MSA|AA|MSG0001', $response->getContent());

        $item = $order->items->first(fn ($i) => $i->test->code === 'FBC')->fresh('results');
        $this->assertSame('resulted', $item->status);
        $this->assertSame($analyzer->user_id, $item->entered_by);
        $this->assertSame('low', $item->results->firstWhere('name', 'Haemoglobin')->flag);
        $this->assertSame('partial', IntegrationMessage::where('channel', 'lab')->latest('id')->first()->status); // RDW unmapped

        // JSON second message merges (does not wipe haemoglobin).
        $this->withToken($token)->postJson('/api/v1/lab/results', ['sample_id' => $order->order_number, 'results' => [['code' => 'WBC', 'value' => '7.0']]])
            ->assertOk()->assertJson(['status' => 'ok']);
        $item->refresh()->load('results');
        $this->assertSame('9.1', $item->results->firstWhere('name', 'Haemoglobin')->value);
        $this->assertSame('7.0', $item->results->firstWhere('name', 'WBC')->value);

        // Bad token / unknown sample are refused.
        $this->withToken('lab_wrong')->postJson('/api/v1/lab/results', ['sample_id' => 'X', 'results' => [['code' => 'HGB', 'value' => '1']]])->assertUnauthorized();
        $this->withToken($token)->postJson('/api/v1/lab/results', ['sample_id' => 'LAB-NOPE', 'results' => [['code' => 'HGB', 'value' => '1']]])->assertStatus(422);

        // A scientist verifies (the analyser counts as the one who entered them).
        $rdt = $order->items->first(fn ($i) => $i->test->code === 'MRDT');
        $verifier = User::factory()->create()->assignRole('Lab Scientist');
        $this->actingAs($verifier)->post(route('lab.results', $order), ['results' => [$rdt->id => [$rdt->test->parameters->first()->id => 'Negative']]])->assertSessionHasNoErrors();
        $other = User::factory()->create()->assignRole('Lab Scientist');
        $this->actingAs($other)->post(route('lab.verify', $order))->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);

        // Verified results can't be overwritten by the analyser.
        $this->withToken($token)->postJson('/api/v1/lab/results', ['sample_id' => $order->order_number, 'results' => [['code' => 'HGB', 'value' => '15']]])->assertStatus(422);
    }

    public function test_hl7_parser_handles_segments_and_ack(): void
    {
        $parsed = Hl7Message::parse("MSH|^~\\&|APP|FAC|||20260101||ORU^R01|42|P|2.5\nORC|RE|PL1|LAB-9\nOBR|1|||X\nOBX|1|NM|K^Potassium||4.1|mmol/L|||||F\nOBX|2|NM|NA||| ||||X");
        $this->assertSame('42', $parsed['control_id']);
        $this->assertSame([['code' => 'K', 'name' => 'Potassium', 'value' => '4.1', 'unit' => 'mmol/L']], $parsed['samples']['LAB-9']);
        $this->assertStringContainsString("MSA|AE|42|bad input", Hl7Message::ack('42', false, 'bad|input'));
    }

    // ------------------------------------------------------------------ PACS

    public function test_pacs_links_study_marks_performed_and_gives_viewer_link(): void
    {
        $this->secrets(['pacs_enabled' => '1', 'pacs_dicomweb_url' => 'http://pacs.local/dicom-web', 'pacs_viewer_url' => 'http://pacs.local/viewer?StudyInstanceUIDs={study}',
            'pacs_username' => 'orthanc', 'pacs_password' => 'secret']);
        $this->actingAs($this->doctor)->post(route('consultations.imaging.store', $this->consultation), [
            'imaging_test_id' => ImagingTest::first()->id, 'priority' => 'routine', 'clinical_notes' => 'Cough',
        ])->assertSessionHasNoErrors();
        $order = ImagingOrder::sole();

        Http::fake(['pacs.local/dicom-web/studies*' => Http::sequence()
            ->push(null, 204)
            ->push([['0020000D' => ['vr' => 'UI', 'Value' => ['1.2.840.1']]]], 200)]);

        $this->artisan('emr:pacs-sync')->assertSuccessful();
        $this->assertNull($order->fresh()->study_instance_uid);

        $this->artisan('emr:pacs-sync')->assertSuccessful();
        $order->refresh();
        $this->assertSame('1.2.840.1', $order->study_instance_uid);
        $this->assertSame('performed', $order->status);
        $this->assertSame('PACS link', $order->performer->name);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'AccessionNumber='.$order->order_number) && $r->hasHeader('Authorization'));

        $radiographer = User::factory()->create()->assignRole('Radiographer');
        $this->actingAs($radiographer)->get(route('radiology.show', $order))->assertOk()
            ->assertSee('View images')->assertSee('http://pacs.local/viewer?StudyInstanceUIDs=1.2.840.1', false);
    }

    // ------------------------------------------------------------------ online payments

    protected function priceConsultation(float $amount): BillItem
    {
        $service = Service::where('code', 'REG')->sole();
        Price::create(['billable_type' => $service->getMorphClass(), 'billable_id' => $service->id, 'amount' => $amount]);

        return app(\App\Services\BillingService::class)->chargeRegistration($this->patient, $this->admin);
    }

    public function test_paystack_portal_payment_is_verified_before_posting_and_is_idempotent(): void
    {
        $item = $this->priceConsultation(5000);
        $this->secrets(['payment_gateway' => 'paystack', 'payment_secret_key' => 'sk_test_x', 'portal_enabled' => '1', 'portal_online_payments' => '1']);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc']]),
            'api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => 500000, 'currency' => 'NGN', 'id' => 777, 'gateway_response' => 'Approved']]),
        ]);

        $code = app(PortalService::class)->issueAccess($this->patient, $this->admin);
        app(PortalService::class)->activate($this->patient->hospital_number, $code, 'secret123');
        auth()->guard('web')->logout();
        $account = PatientAccount::sole();

        $this->actingAs($account, 'patient')->get(route('portal.bills'))->assertSee('online');
        $this->actingAs($account, 'patient')->post(route('portal.bills.pay'))->assertRedirect('https://checkout.paystack.com/abc');
        $online = OnlinePayment::sole();
        $this->assertEquals(5000, $online->amount);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'initialize') && $r['amount'] === 500000 && $r['reference'] === $online->reference);

        // Callback: verified with Paystack server-side, then posted.
        $this->get(route('payments.callback', ['reference' => $online->reference]))->assertRedirect(route('portal.bills'));
        $this->assertSame('success', $online->fresh()->status);
        $payment = Payment::sole();
        $this->assertSame('online', $payment->method);
        $this->assertNull($payment->received_by);
        $this->assertEquals(0, $item->fresh()->outstanding());

        // Webhook for the same payment: signature checked, nothing posted twice.
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $online->reference]]);
        $this->call('POST', '/api/v1/payments/webhook/paystack', [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => 'bad', 'CONTENT_TYPE' => 'application/json'], $body)->assertUnauthorized();
        $this->call('POST', '/api/v1/payments/webhook/paystack', [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_x'), 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();
        $this->assertSame(1, Payment::count());
    }

    public function test_cashier_payment_link_with_flutterwave_and_overpayment_to_deposit(): void
    {
        $item = $this->priceConsultation(3000);
        $this->secrets(['payment_gateway' => 'flutterwave', 'payment_secret_key' => 'FLWSECK-x', 'payment_webhook_hash' => 'hash123']);
        $cashier = User::factory()->create()->assignRole('Cashier');

        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/pay/x']]),
            'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response(['status' => 'success', 'data' => ['status' => 'successful', 'amount' => 3000, 'currency' => 'NGN', 'id' => 55]]),
        ]);

        $this->actingAs($cashier)->post(route('billing.payment-link', $this->patient), ['send_sms' => '1'])->assertSessionHas('payment_link');
        $online = OnlinePayment::sole();
        $this->assertSame('link', $online->channel);
        $this->assertSame(1, \App\Models\SmsMessage::where('type', 'payment_link')->count());

        // Meanwhile the patient pays cash for the same item…
        $this->actingAs($cashier)->post(route('billing.pay', $this->patient), ['items' => [$item->id], 'amount' => 3000, 'method' => 'cash'])->assertSessionHasNoErrors();
        auth()->guard('web')->logout();

        // …then completes the link: nothing is lost — it becomes a deposit.
        $this->get(route('payments.pay', $online->reference))->assertRedirect('https://checkout.flutterwave.com/pay/x');
        $this->withHeaders(['verif-hash' => 'hash123'])->postJson('/api/v1/payments/webhook/flutterwave', ['data' => ['tx_ref' => $online->reference]])->assertOk();
        $this->assertSame('success', $online->fresh()->status);
        $this->assertEquals(3000, app(\App\Services\BillingService::class)->depositBalance($this->patient));
        $this->get(route('payments.pay', $online->reference))->assertOk()->assertSee('was received');

        // Cashiers cannot record "online" by hand.
        $this->actingAs($cashier)->post(route('billing.deposit', $this->patient), ['amount' => 10, 'method' => 'online'])->assertSessionHasErrorsIn('deposit', 'method');
    }

    // ------------------------------------------------------------------ e-claims

    public function test_electronic_claim_file_and_submission(): void
    {
        $hmo = InsuranceProvider::factory()->create(['code' => 'NHIA', 'coverage_percent' => 90]);
        $insured = Patient::factory()->create(['payment_type' => 'insurance', 'insurance_provider_id' => $hmo->id, 'insurance_number' => 'NH-1']);
        $consult = Service::where('code', 'CONSULT')->sole();
        Price::create(['billable_type' => $consult->getMorphClass(), 'billable_id' => $consult->id, 'amount' => 10000]);
        $visit = app(QueueService::class)->checkIn($insured, ['clinic_id' => Clinic::factory()->create()->id], $this->admin);
        $visit->forceFill(['status' => Visit::COMPLETED])->save();
        $accountant = User::factory()->create()->assignRole('Accountant');
        $batch = app(ClaimService::class)->createBatch($hmo, [\App\Models\Bill::where('visit_id', $visit->id)->value('id')], $accountant);

        $json = $this->actingAs($accountant)->get(route('billing.claims.electronic', $batch))->assertOk()->json();
        $this->assertSame('emr-claim-1.0', $json['format']);
        $this->assertSame('NH-1', $json['claims'][0]['enrollee']['id']);
        $this->assertEquals(9000, $json['claims'][0]['total_claimed']);

        $this->secrets(['claims_api_url' => 'https://claims.example.ng/api/batches', 'claims_api_key' => 'k123', 'claims_provider_code' => 'FAC-001']);
        $this->actingAs($accountant)->post(route('billing.claims.send', $batch))->assertSessionHasErrors('batch'); // still draft

        app(ClaimService::class)->submit($batch, $accountant);
        Http::fake(['claims.example.ng/*' => Http::response(['reference' => 'NHIA-REF-9'], 201)]);
        $this->actingAs($accountant)->post(route('billing.claims.send', $batch))->assertSessionHasNoErrors();
        $batch->refresh();
        $this->assertSame('sent', $batch->submission_status);
        $this->assertSame('NHIA-REF-9', $batch->submission_reference);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer k123') && $r['provider']['code'] === 'FAC-001');
    }

    // ------------------------------------------------------------------ NIN

    public function test_nin_lookup_fills_form_and_marks_patient_verified(): void
    {
        $records = User::factory()->create()->assignRole('Records Officer');
        $this->secrets(['nin_provider' => 'dojah', 'nin_app_id' => 'app1', 'nin_api_key' => 'dojah-secret']);
        Http::fake(['api.dojah.io/*' => Http::response(['entity' => [
            'first_name' => 'AMINA', 'middle_name' => 'HAUWA', 'last_name' => 'BELLO', 'date_of_birth' => '1985-04-12', 'gender' => 'F', 'phone_number' => '2348031234567',
        ]])]);

        $this->actingAs($records)->postJson(route('patients.nin'), ['national_id' => '123'])->assertStatus(422);
        $this->actingAs($records)->postJson(route('patients.nin'), ['national_id' => '12345678901'])->assertOk()
            ->assertJson(['first_name' => 'Amina', 'last_name' => 'Bello', 'gender' => 'female', 'date_of_birth' => '1985-04-12', 'phone' => '08031234567']);
        Http::assertSent(fn ($r) => $r->hasHeader('AppId', 'app1') && $r->hasHeader('Authorization', 'dojah-secret'));

        $log = IntegrationMessage::where('channel', 'nin')->latest('id')->first();
        $this->assertStringContainsString('123*****901', $log->summary);
        $this->assertStringNotContainsString('12345678901', $log->summary.$log->payload);

        $this->patient->update(['national_id' => '12345678901']);
        $this->actingAs($records)->put(route('patients.update', $this->patient), array_merge($this->patient->only([
            'first_name', 'last_name', 'gender', 'payment_type']), ['national_id' => '12345678901', 'date_of_birth' => '1985-04-12']))->assertSessionHasNoErrors();
        $this->assertNotNull($this->patient->fresh()->nin_verified_at);
    }

    public function test_integration_settings_are_encrypted_and_admin_page_works(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.update', 'integrations'), [
            'payment_gateway' => 'paystack', 'payment_secret_key' => 'sk_live_supersecret', 'nin_provider' => 'none', 'pacs_enabled' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertNotSame('sk_live_supersecret', setting('payment_secret_key'));
        $this->assertSame('sk_live_supersecret', \App\Services\SmsService::secret('payment_secret_key'));
        $this->actingAs($this->admin)->get(route('admin.settings.edit', ['section' => 'integrations']))->assertOk()->assertDontSee('sk_live_supersecret');

        $this->actingAs($this->admin)->get(route('admin.integrations.index'))->assertOk()->assertSee('Lab analysers');
        $this->actingAs($this->doctor)->get(route('admin.integrations.index'))->assertForbidden();
    }
}
