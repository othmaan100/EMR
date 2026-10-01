<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Price;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\QueueService;
use App\Services\ReportService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->seed(CatalogSeeder::class);
        Carbon::setTestNow('2026-10-10 09:00:00');

        $this->admin = User::factory()->create()->assignRole('Administrator');
        $doctor = User::factory()->create()->assignRole('Doctor');
        $cashier = User::factory()->create()->assignRole('Cashier');
        $consult = Service::where('code', 'CONSULT')->first();
        Price::create(['billable_type' => $consult->getMorphClass(), 'billable_id' => $consult->id, 'amount' => 3000]);

        $clinic = Clinic::factory()->create(['name' => 'General OPD', 'requires_triage' => false]);

        // Two consultations with a malaria diagnosis, one child and one adult.
        foreach ([['female', 3], ['male', 30]] as [$gender, $age]) {
            $patient = Patient::factory()->create(['gender' => $gender, 'date_of_birth' => now()->subYears($age)]);
            $visit = app(QueueService::class)->checkIn($patient, ['clinic_id' => $clinic->id], $doctor);
            Carbon::setTestNow(now()->addMinutes(20));
            $this->actingAs($doctor)->patch(route('visits.move', $visit), ['status' => Visit::IN_CONSULTATION]);
            $this->actingAs($doctor)->get(route('consultations.show', $visit));
            $c = Consultation::where('visit_id', $visit->id)->first();
            $this->actingAs($doctor)->post(route('consultations.diagnoses.store', $c), ['icd10_code' => 'B50.9', 'description' => 'Plasmodium falciparum malaria, unspecified', 'certainty' => 'confirmed']);
            $this->actingAs($doctor)->post(route('consultations.lab.store', $c), ['tests' => [LabTest::where('code', 'MRDT')->value('id')], 'priority' => 'routine']);
            $this->actingAs($doctor)->put(route('consultations.update', $c), ['presenting_complaint' => 'Fever']);
            $this->actingAs($doctor)->post(route('consultations.sign', $c))->assertSessionHasNoErrors();
        }

        $this->actingAs($cashier)->post(route('billing.pay', Patient::first()), [
            'items' => Patient::first()->bills()->first()->items->pluck('id')->all(), 'amount' => 3000, 'method' => 'cash',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_hub_shows_overview_and_permitted_reports(): void
    {
        $this->actingAs($this->admin)->get(route('reports.index'))
            ->assertOk()->assertSee('Top diagnoses')->assertSee('Staff activity')->assertSee('Money collected');

        $doctor = User::factory()->create()->assignRole('Doctor');
        $this->actingAs($doctor)->get(route('reports.index'))
            ->assertOk()->assertSee('Top diagnoses')->assertDontSee('Revenue by service')->assertDontSee('Money collected');
    }

    public function test_every_report_renders(): void
    {
        foreach (array_keys(ReportService::REPORTS) as $key) {
            $this->actingAs($this->admin)->get(route('reports.show', ['report' => $key, 'from' => '2026-10-01', 'to' => '2026-10-31']))
                ->assertOk();
        }
    }

    public function test_diagnosis_report_counts_by_age_and_sex(): void
    {
        $result = app(ReportService::class)->run('diagnoses', Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        $row = $result['rows'][0];

        $this->assertSame('B50.9', $row['code']);
        $this->assertSame(2, $row['cases']);
        $this->assertSame(1, $row['u5']);
        $this->assertSame(1, $row['15_49']);
        $this->assertSame(1, $row['male']);
        $this->assertSame(1, $row['female']);
    }

    public function test_visit_waits_revenue_and_collections(): void
    {
        $svc = app(ReportService::class);
        $range = [Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')];

        $visits = $svc->run('visits', ...$range);
        $this->assertSame(2, $visits['rows'][0]['visits']);
        $this->assertSame(20.0, $visits['rows'][0]['avg_wait']);

        $revenue = collect($svc->run('revenue', ...$range)['rows'])->keyBy('category');
        $this->assertSame(6000.0, $revenue['Consultation']['amount']);

        $collections = collect($svc->run('collections', ...$range)['rows'])->firstWhere('date', '2026-10-10');
        $this->assertSame(3000.0, $collections['total']);
        $this->assertSame(3000.0, $collections['cash']);

        $outstanding = $svc->run('outstanding', ...$range);
        $this->assertSame(3000.0, $outstanding['rows'][0]['total']);
        $this->assertSame(3000.0, $outstanding['rows'][0]['d0_30']);

        $staff = collect($svc->run('staff', ...$range)['rows']);
        $this->assertSame(2, $staff->sum('consultations'));
        $this->assertSame(1, $staff->sum('receipts'));
    }

    public function test_date_range_excludes_other_periods(): void
    {
        $result = app(ReportService::class)->run('diagnoses', Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
        $this->assertSame([], $result['rows']);
    }

    public function test_csv_export(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.show', ['report' => 'diagnoses', 'from' => '2026-10-01', 'to' => '2026-10-31', 'export' => 'csv']));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('ICD-10,Diagnosis,Cases', $csv);
        $this->assertStringContainsString('B50.9', $csv);
        $this->assertDatabaseHas('audit_logs', ['event' => 'report_exported']);
    }

    public function test_report_permissions(): void
    {
        $accountant = User::factory()->create()->assignRole('Accountant');
        $this->actingAs($accountant)->get(route('reports.show', 'revenue'))->assertOk();
        $this->actingAs($accountant)->get(route('reports.show', 'diagnoses'))->assertForbidden();
        $this->actingAs($accountant)->get(route('reports.show', 'staff'))->assertForbidden();

        $nurse = User::factory()->create()->assignRole('Nurse');
        $this->actingAs($nurse)->get(route('reports.show', 'admissions'))->assertOk();
        $this->actingAs($nurse)->get(route('reports.show', 'collections'))->assertForbidden();

        $lab = User::factory()->create()->assignRole('Lab Scientist');
        $this->actingAs($lab)->get(route('reports.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('reports.show', 'nonsense'))->assertNotFound();
    }
}
