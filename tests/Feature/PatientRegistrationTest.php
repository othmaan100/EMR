<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $records;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        app(Settings::class)->set(['patient_number_prefix' => 'GH', 'patient_number_padding' => 5]);
        $this->records = User::factory()->create()->assignRole('Records Officer');
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'aisha',
            'last_name' => 'MUSA',
            'gender' => 'female',
            'date_of_birth' => '1990-05-12',
            'phone' => '08031234567',
            'payment_type' => 'self_pay',
            'nok_name' => 'Ibrahim Musa',
            'nok_relationship' => 'Spouse',
        ], $overrides);
    }

    public function test_registration_assigns_sequential_hospital_numbers(): void
    {
        $this->actingAs($this->records)->post(route('patients.store'), $this->payload())->assertRedirect();
        $this->actingAs($this->records)->post(route('patients.store'), $this->payload(['first_name' => 'Zainab', 'phone' => '0999']))->assertRedirect();

        $this->assertSame(['GH-00001', 'GH-00002'], Patient::orderBy('id')->pluck('hospital_number')->all());

        $first = Patient::first();
        $this->assertSame('Aisha', $first->first_name); // names normalised
        $this->assertSame('Musa', $first->last_name);
        $this->assertSame($this->records->id, $first->registered_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => Patient::class]);
    }

    public function test_age_can_be_given_instead_of_date_of_birth(): void
    {
        $this->actingAs($this->records)->post(route('patients.store'), $this->payload(['date_of_birth' => null, 'age_years' => 40]));

        $patient = Patient::first();
        $this->assertTrue($patient->dob_estimated);
        $this->assertSame(now()->year - 40, $patient->date_of_birth->year);
        $this->assertSame('40 yrs', $patient->age);
    }

    public function test_either_dob_or_age_is_required(): void
    {
        $this->actingAs($this->records)
            ->post(route('patients.store'), $this->payload(['date_of_birth' => null]))
            ->assertSessionHasErrors('date_of_birth');
    }

    public function test_possible_duplicate_is_flagged_until_confirmed(): void
    {
        Patient::factory()->create(['first_name' => 'Aisha', 'last_name' => 'Musa', 'date_of_birth' => '1990-05-12', 'phone' => '0700']);

        $this->actingAs($this->records)
            ->post(route('patients.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('duplicates');
        $this->assertSame(1, Patient::count());

        $this->actingAs($this->records)->post(route('patients.store'), $this->payload(['confirm_duplicate' => 1]));
        $this->assertSame(2, Patient::count());
    }

    public function test_insurance_patients_need_provider_and_member_number(): void
    {
        $this->actingAs($this->records)
            ->post(route('patients.store'), $this->payload(['payment_type' => 'insurance']))
            ->assertSessionHasErrors(['insurance_provider_id', 'insurance_number']);

        $hmo = InsuranceProvider::factory()->create();
        $this->actingAs($this->records)->post(route('patients.store'), $this->payload([
            'payment_type' => 'insurance', 'insurance_provider_id' => $hmo->id, 'insurance_number' => 'HMO-123',
        ]))->assertSessionHasNoErrors();

        $this->assertSame($hmo->id, Patient::first()->insurance_provider_id);
    }

    public function test_switching_to_self_pay_clears_insurance(): void
    {
        $hmo = InsuranceProvider::factory()->create();
        $patient = Patient::factory()->create(['payment_type' => 'insurance', 'insurance_provider_id' => $hmo->id, 'insurance_number' => 'X1']);

        $this->actingAs($this->records)->put(route('patients.update', $patient), $this->payload(['payment_type' => 'self_pay']));

        $this->assertNull($patient->fresh()->insurance_provider_id);
        $this->assertNull($patient->fresh()->insurance_number);
    }

    public function test_search_by_name_number_and_phone(): void
    {
        $a = Patient::factory()->create(['first_name' => 'Chinedu', 'last_name' => 'Okeke', 'phone' => '08011111111']);
        Patient::factory()->create(['first_name' => 'Fatima', 'last_name' => 'Bello', 'phone' => '08022222222']);

        $this->actingAs($this->records)->get(route('patients.index', ['q' => 'okeke chinedu']))
            ->assertOk()->assertSee('OKEKE')->assertDontSee('BELLO');

        $this->actingAs($this->records)->get(route('patients.index', ['q' => '0802222']))
            ->assertOk()->assertSee('BELLO')->assertDontSee('OKEKE');

        // Exact hospital number jumps straight into the folder.
        $this->actingAs($this->records)->get(route('patients.index', ['q' => $a->hospital_number]))
            ->assertRedirect(route('patients.show', $a));
    }

    public function test_viewing_a_record_is_audited(): void
    {
        $patient = Patient::factory()->create();

        $this->actingAs($this->records)->get(route('patients.show', $patient))->assertOk()->assertSee($patient->hospital_number);

        $this->assertTrue(AuditLog::where('event', 'patient_viewed')->where('auditable_id', $patient->id)->where('user_id', $this->records->id)->exists());
    }

    public function test_photo_upload_is_private_and_served_only_to_authorised_users(): void
    {
        Storage::fake('local');

        $this->actingAs($this->records)->post(route('patients.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('face.jpg', 300, 300),
        ]));
        $patient = Patient::first();
        Storage::disk('local')->assertExists($patient->photo);

        $this->actingAs($this->records)->get(route('patients.photo', $patient))->assertOk();

        $noRole = User::factory()->create(); // no patients.view
        $this->actingAs($noRole)->get(route('patients.photo', $patient))->assertForbidden();
    }

    public function test_webcam_photo_data_url_is_accepted(): void
    {
        Storage::fake('local');
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagejpeg($img);
        $dataUrl = 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());

        $this->actingAs($this->records)->post(route('patients.store'), $this->payload(['photo_data' => $dataUrl]))->assertSessionHasNoErrors();

        Storage::disk('local')->assertExists(Patient::first()->photo);
    }

    public function test_permissions_are_enforced(): void
    {
        $patient = Patient::factory()->create();
        $doctor = User::factory()->create()->assignRole('Doctor'); // view only

        $this->actingAs($doctor)->get(route('patients.show', $patient))->assertOk();
        $this->actingAs($doctor)->get(route('patients.create'))->assertForbidden();
        $this->actingAs($doctor)->put(route('patients.update', $patient), $this->payload())->assertForbidden();
        $this->actingAs($this->records)->delete(route('patients.destroy', $patient))->assertForbidden();

        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->delete(route('patients.destroy', $patient))->assertRedirect(route('patients.index'));
        $this->assertSoftDeleted($patient);
    }

    public function test_patient_pages_render(): void
    {
        $patient = Patient::factory()->create(['allergies' => 'Penicillin', 'blood_group' => 'O+']);

        $this->actingAs($this->records)->get(route('patients.index'))->assertOk();
        $this->actingAs($this->records)->get(route('patients.create'))->assertOk();
        $this->actingAs($this->records)->get(route('patients.edit', $patient))->assertOk();
        $this->actingAs($this->records)->get(route('patients.card', $patient))->assertOk()->assertSee($patient->hospital_number);
        $this->actingAs($this->records)->get(route('patients.show', $patient))->assertOk()->assertSee('Penicillin');
    }

    public function test_insurance_provider_admin(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)->post(route('admin.insurance.store'), [
            'name' => 'National Health Insurance', 'code' => 'nhia', 'type' => 'insurance', 'is_active' => 1,
        ])->assertRedirect(route('admin.insurance.index'));

        $hmo = InsuranceProvider::where('code', 'NHIA')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.insurance.index'))->assertOk()->assertSee('National Health Insurance');
        $this->actingAs($admin)->get(route('admin.insurance.edit', $hmo))->assertOk();

        Patient::factory()->create(['payment_type' => 'insurance', 'insurance_provider_id' => $hmo->id]);
        $this->actingAs($admin)->delete(route('admin.insurance.destroy', $hmo))->assertSessionHas('error');

        $this->actingAs($this->records)->get(route('admin.insurance.index'))->assertForbidden();
    }
}
