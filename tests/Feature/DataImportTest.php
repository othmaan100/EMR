<?php

namespace Tests\Feature;

use App\Imports\ImportRegistry;
use App\Imports\SpreadsheetFile;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\BillItem;
use App\Models\Clinic;
use App\Models\DataImport;
use App\Models\Department;
use App\Models\Drug;
use App\Models\Immunization;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\Price;
use App\Models\Service;
use App\Models\StockBatch;
use App\Models\StockReceipt;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DataImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        Storage::fake('local'); // uploaded files never touch real storage
        $this->admin = User::factory()->create()->assignRole('Administrator');
    }

    protected function csv(array $rows): UploadedFile
    {
        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);

        return UploadedFile::fake()->createWithContent('import.csv', stream_get_contents($out));
    }

    /**
     * Upload, then return the analysed import.
     */
    protected function upload(string $type, UploadedFile $file, string $mode = 'create', array $options = []): DataImport
    {
        $this->actingAs($this->admin)->post(route('admin.imports.upload', $type), ['file' => $file, 'mode' => $mode, 'options' => $options])
            ->assertSessionHasNoErrors()->assertRedirect();

        return DataImport::latest('id')->firstOrFail();
    }

    protected function runImport(DataImport $import): DataImport
    {
        $this->actingAs($this->admin)->post(route('admin.imports.run', $import))->assertRedirect(route('admin.imports.review', $import));

        return $import->fresh();
    }

    public function test_every_import_type_has_templates_matching_its_columns(): void
    {
        $this->actingAs($this->admin)->get(route('admin.imports.index'))->assertOk()->assertSee('Patients')->assertSee('Staff accounts');

        foreach (ImportRegistry::all() as $key => $importer) {
            $this->actingAs($this->admin)->get(route('admin.imports.show', $key))->assertOk()->assertSee($importer->title());

            $csv = $this->actingAs($this->admin)->get(route('admin.imports.template', [$key, 'format' => 'csv']))->assertOk()->getContent();
            $this->assertSame(array_map(fn ($c) => $c->name, $importer->columns()), str_getcsv(trim(substr($csv, 3))), "CSV template for {$key}");

            $response = $this->actingAs($this->admin)->get(route('admin.imports.template', [$key, 'format' => 'xlsx', 'sample' => 1]))->assertOk();
            $read = SpreadsheetFile::read($response->getFile()->getPathname(), 'xlsx');
            $this->assertSame(array_map(fn ($c) => $c->name, $importer->columns()), $read['headers'], "XLSX template for {$key}");
            $this->assertNotEmpty($read['rows'], "Sample rows for {$key}");
        }
    }

    public function test_patient_import_preview_then_run_with_messy_legacy_data(): void
    {
        InsuranceProvider::factory()->create(['code' => 'HYG', 'coverage_percent' => 100]);

        $file = $this->csv([
            ['hospital_number', 'legacy_number', 'first_name', 'last_name', 'gender', 'date_of_birth', 'age_years', 'phone', 'insurer_code', 'insurance_number', 'registered_on', 'blood_group'],
            ['PT-000500', '', 'Amina', 'Bello', 'F', '12/04/1985', '', '08031234567', 'HYG', 'HYG/1', '01/06/2015', 'O Positive'],
            ['', 'OLD-77', 'Chinedu', 'Okafor', 'm', '', '7', '', '', '', '', ''],
            ['', 'OLD-78', 'No', '', 'female', '', '', '', '', '', '', ''],                  // surname missing
            ['', 'OLD-79', 'Bad', 'Date', 'female', '31/02/1990', '', '', '', '', '', ''],     // impossible date
            ['', 'OLD-80', 'Ghost', 'Payer', 'male', '', '', '', 'XYZ', '', '', ''],           // unknown insurer
            ['', 'OLD-77', 'Chinedu', 'Again', 'male', '', '', '', '', '', '', ''],            // duplicate of row 3
        ]);

        $import = $this->upload('patients', $file);
        $this->assertSame('validated', $import->status);
        $this->assertSame([6, 2, 4], [$import->total_rows, $import->create_rows, $import->error_rows]);
        $this->assertSame(0, Patient::count(), 'Preview must not save anything');
        $this->actingAs($this->admin)->get(route('admin.imports.review', $import))->assertOk()
            ->assertSee('Will be added')->assertSee('Same record as row 3')->assertSee('Insurer &quot;XYZ&quot; not found', false);

        $report = $this->actingAs($this->admin)->get(route('admin.imports.errors', $import))->assertOk()->getContent();
        $this->assertStringContainsString('errors', $report);
        $this->assertStringContainsString('OLD-79', $report);

        $import = $this->runImport($import);
        $this->assertSame('completed', $import->status);
        $this->assertSame(2, $import->created_count);
        $this->assertNull($import->stored_path);

        $amina = Patient::where('hospital_number', 'PT-000500')->sole();
        $this->assertSame('female', $amina->gender);
        $this->assertSame('1985-04-12', $amina->date_of_birth->toDateString());
        $this->assertSame('insurance', $amina->payment_type);
        $this->assertSame('O+', $amina->blood_group);
        $this->assertSame('2015-06-01', $amina->created_at->toDateString());

        $chinedu = Patient::where('legacy_number', 'OLD-77')->sole();
        $this->assertTrue($chinedu->dob_estimated);
        $this->assertSame(7, $chinedu->ageInYears());

        // One summary audit entry, not one per patient.
        $this->assertSame(0, AuditLog::where('event', 'created')->where('auditable_type', (new Patient)->getMorphClass())->count());
        $this->assertSame(1, AuditLog::where('event', 'data_imported')->count());

        // Numbers issued during and after the import continue above the imported PT-000500.
        $this->assertSame('PT-000501', $chinedu->hospital_number);
        $this->assertStringEndsWith('-000502', Patient::factory()->create()->hospital_number);
    }

    public function test_update_mode_changes_given_fields_and_keeps_blank_ones(): void
    {
        $patient = Patient::factory()->create(['legacy_number' => 'OLD-1', 'phone' => '0801', 'occupation' => 'Farmer']);

        $skip = $this->upload('patients', $this->csv([['legacy_number', 'first_name', 'last_name', 'gender', 'phone'], ['OLD-1', 'X', 'Y', 'male', '0809']]));
        $this->assertSame(1, $skip->skip_rows);
        $this->actingAs($this->admin)->post(route('admin.imports.cancel', $skip))->assertRedirect();
        $this->assertSame('cancelled', $skip->fresh()->status);

        $import = $this->upload('patients', $this->csv([['legacy_number', 'first_name', 'last_name', 'gender', 'phone', 'occupation'],
            ['OLD-1', $patient->first_name, $patient->last_name, $patient->gender, '08099999999', '']]), 'update');
        $this->assertSame(1, $import->update_rows);
        $this->runImport($import);

        $patient->refresh();
        $this->assertSame('08099999999', $patient->phone);
        $this->assertSame('Farmer', $patient->occupation);
    }

    public function test_xlsx_upload_with_dates_and_numeric_phones(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['First Name*', 'Last Name', 'Gender', 'Date of Birth', 'Phone', 'Legacy Number']));
        $writer->addRow(Row::fromValues(['Musa', 'Ibrahim', 'Male', new \DateTimeImmutable('1990-01-15'), 8031112222, 'X-1']));
        $writer->close();

        $import = $this->upload('patients', new UploadedFile($path, 'legacy.xlsx', null, null, true));
        $this->assertSame(1, $import->create_rows, json_encode($import->errors));
        $this->runImport($import);

        $musa = Patient::where('legacy_number', 'X-1')->sole();
        $this->assertSame('1990-01-15', $musa->date_of_birth->toDateString());
        $this->assertSame('08031112222', $musa->phone); // leading 0 restored
    }

    public function test_staff_import_roles_departments_and_temporary_password(): void
    {
        Department::create(['name' => 'Laboratory', 'code' => 'LAB', 'type' => 'Diagnostic']);

        $file = $this->csv([
            ['name', 'username', 'roles', 'email', 'department_code', 'password'],
            ['Grace Okon', 'GOkon', 'Lab Scientist', 'grace@h.ng', 'LAB', ''],
            ['Dr Musa', 'dmusa', 'doctor, anaesthetist', 'musa@h.ng', '', 'Str0ngPass!'],
            ['Sneaky', 'sneaky', 'Super Admin', 's@h.ng', '', ''],
            ['Nobody', 'nobody', 'Wizard', 'n@h.ng', '', ''],
        ]);
        $import = $this->upload('staff', $file, 'create', ['default_password' => 'Welcome123!']);

        $this->assertSame([2, 2], [$import->create_rows, $import->error_rows]);
        $this->assertArrayNotHasKey('default_password', $import->options);
        $this->assertStringNotContainsString('Welcome123!', json_encode($import->options));

        $import = $this->runImport($import);
        $this->assertSame(2, $import->created_count, json_encode($import->errors));
        $grace = User::where('username', 'gokon')->sole();
        $this->assertTrue($grace->hasRole('Lab Scientist'));
        $this->assertSame('LAB', $grace->department->code);
        $this->assertTrue((bool) $grace->must_change_password);
        $this->assertTrue(Hash::check('Welcome123!', $grace->password));

        $musa = User::where('username', 'dmusa')->sole();
        $this->assertTrue($musa->hasAllRoles(['Doctor', 'Anaesthetist']));
        $this->assertTrue(Hash::check('Str0ngPass!', $musa->password));

        // Without a temporary password, new accounts need one in the file.
        $noPw = $this->upload('staff', $this->csv([['name', 'username', 'roles', 'email'], ['A B', 'abee', 'Nurse', 'ab@h.ng']]));
        $this->assertSame(1, $noPw->error_rows);
    }

    public function test_catalogues_prices_wards_and_drug_opening_stock(): void
    {
        $this->seed(CatalogSeeder::class);
        InsuranceProvider::factory()->create(['code' => 'HYG']);

        $this->runImport($this->upload('services', $this->csv([['code', 'name', 'category'], ['dress', 'Wound dressing', 'nursing'], ['BED-G', 'General bed', 'Accommodation']])));
        $this->assertSame('DRESS', Service::where('name', 'Wound dressing')->value('code'));

        $this->runImport($this->upload('wards', $this->csv([['code', 'name', 'type', 'number_of_beds', 'bed_charge_service_code'], ['GMW', 'General Ward', 'General', '4', 'BED-G']])));
        $ward = Ward::where('code', 'GMW')->sole();
        $this->assertSame(['G1', 'G2', 'G3', 'G4'], $ward->beds()->orderBy('id')->pluck('label')->all());

        $prices = $this->upload('prices', $this->csv([
            ['item_type', 'item', 'payer_code', 'amount'],
            ['service', 'DRESS', '', '1,500'],
            ['service', 'DRESS', 'HYG', '1200'],
            ['drug', 'Paracetamol 500mg Tablet', '', '10'],
            ['lab', 'FBC', '', '2500'],
            ['service', 'NOPE', '', '5'],
        ]));
        $this->assertSame([4, 1], [$prices->create_rows, $prices->error_rows], json_encode($prices->errors));
        $this->runImport($prices);
        $dressing = Service::where('code', 'DRESS')->sole();
        $this->assertEquals(1500, $dressing->priceFor(null));
        $this->assertEquals(1200, $dressing->priceFor(InsuranceProvider::where('code', 'HYG')->value('id')));

        $stockFile = fn () => $this->csv([
            ['drug', 'batch_number', 'expiry_date', 'quantity', 'unit_cost'],
            ['Paracetamol 500mg Tablet', 'P1', now()->addYear()->format('d/m/Y'), '1000', '4'],
            ['Amoxicillin 500mg Capsule', 'A1', now()->addYear()->toDateString(), '200', '20'],
            ['Paracetamol 500mg Tablet', 'OLD', now()->subDay()->toDateString(), '50', '4'],
        ]);
        $stock = $this->upload('drug-stock', $stockFile());
        $this->assertSame([2, 1], [$stock->create_rows, $stock->error_rows]);
        $this->runImport($stock);
        $this->assertSame(1, StockReceipt::count());
        $this->assertSame(1000, Drug::where('name', 'Paracetamol')->where('form', 'Tablet')->sole()->usableStock());

        // Re-uploading the same file skips existing batches (no double counting).
        $again = $this->upload('drug-stock', $stockFile());
        $this->assertSame([0, 2], [$again->create_rows, $again->skip_rows]);
        $this->assertSame(2, StockBatch::count());
    }

    public function test_balances_immunizations_and_appointments(): void
    {
        $this->seed(CatalogSeeder::class);
        $patient = Patient::factory()->create(['legacy_number' => 'OLD-5', 'date_of_birth' => now()->subMonths(3)]);
        Clinic::factory()->create(['code' => 'GOPD']);

        $this->runImport($this->upload('patient-balances', $this->csv([['hospital_number', 'amount'], ['OLD-5', '₦12,500.00'], ['NOBODY', '10']])));
        $this->assertEquals(12500, $patient->outstandingBalance());
        $this->assertSame(1, BillItem::count());

        $this->runImport($this->upload('patient-balances', $this->csv([['hospital_number', 'amount'], ['OLD-5', '9000']]), 'update'));
        $this->assertEquals(9000, $patient->outstandingBalance());

        $this->runImport($this->upload('immunizations', $this->csv([['hospital_number', 'vaccine_code', 'given_on'],
            [$patient->hospital_number, 'bcg', now()->subMonths(3)->addDay()->toDateString()], ['OLD-5', 'BCG', now()->toDateString()]])));
        $this->assertSame(1, Immunization::where('patient_id', $patient->id)->count());

        $appts = $this->upload('appointments', $this->csv([['hospital_number', 'clinic_code', 'date', 'time'],
            ['OLD-5', 'gopd', now()->addDays(3)->toDateString(), '2:30 PM'], ['OLD-5', 'GOPD', now()->subDay()->toDateString(), '09:00']]));
        $this->assertSame([1, 1], [$appts->create_rows, $appts->error_rows]);
        $this->runImport($appts);
        $this->assertSame('14:30', Appointment::sole()->scheduled_at->format('H:i'));
    }

    public function test_bad_files_permissions_and_cli(): void
    {
        // Missing required heading → rejected before any row is read.
        $import = $this->upload('patients', $this->csv([['first_name', 'gender'], ['A', 'male']]));
        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('last_name', $import->message);

        $this->actingAs($this->admin)->post(route('admin.imports.upload', 'patients'), ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), 'mode' => 'create'])
            ->assertSessionHasErrors('file');

        $doctor = User::factory()->create()->assignRole('Doctor');
        $this->actingAs($doctor)->get(route('admin.imports.index'))->assertForbidden();

        // data.import alone cannot create staff accounts.
        $role = Role::create(['name' => 'Data Clerk']);
        $role->givePermissionTo('data.import');
        $clerk = User::factory()->create()->assignRole($role);
        $this->actingAs($clerk)->get(route('admin.imports.show', 'patients'))->assertOk();
        $this->actingAs($clerk)->get(route('admin.imports.show', 'staff'))->assertForbidden();

        // Command line: check only, then commit.
        $path = tempnam(sys_get_temp_dir(), 'c').'.csv';
        file_put_contents($path, "first_name,last_name,gender,legacy_number\nAda,Obi,female,CLI-1\n");
        $super = User::factory()->create(['username' => 'root_admin'])->assignRole('Super Admin');
        $this->artisan('emr:import', ['type' => 'patients', 'file' => $path, '--user' => 'root_admin'])->assertSuccessful();
        $this->assertSame(0, Patient::where('legacy_number', 'CLI-1')->count());
        $this->artisan('emr:import', ['type' => 'patients', 'file' => $path, '--user' => 'root_admin', '--commit' => true])->assertSuccessful();
        $this->assertSame(1, Patient::where('legacy_number', 'CLI-1')->count());
    }
}
