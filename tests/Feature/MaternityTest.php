<?php

namespace Tests\Feature;

use App\Models\AncVisit;
use App\Models\BillItem;
use App\Models\Delivery;
use App\Models\Patient;
use App\Models\Pregnancy;
use App\Models\Price;
use App\Models\Service;
use App\Models\User;
use App\Models\Vaccine;
use App\Services\ImmunizationService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaternityTest extends TestCase
{
    use RefreshDatabase;

    protected User $midwife;

    protected Patient $mother;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->seed(CatalogSeeder::class);
        Carbon::setTestNow('2026-10-10 10:00:00');

        $this->midwife = User::factory()->create()->assignRole('Midwife');
        $this->mother = Patient::factory()->create(['gender' => 'female', 'date_of_birth' => '1996-03-01', 'last_name' => 'Bello', 'phone' => '08030000000']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function book(array $overrides = []): Pregnancy
    {
        $this->actingAs($this->midwife)->post(route('maternity.store', $this->mother), array_merge([
            'lmp' => '2026-03-01', 'gravida' => 2, 'parity' => 1, 'abortions' => 0, 'living_children' => 1,
        ], $overrides))->assertSessionHasNoErrors();

        return Pregnancy::latest('id')->firstOrFail();
    }

    public function test_booking_calculates_edd_and_gestation(): void
    {
        $p = $this->book(['risk_factors' => ['previous_cs']]);

        $this->assertSame('2026-12-06', $p->edd->toDateString()); // LMP + 280 days
        $this->assertSame('31w 6d', $p->gestationLabel());         // 1 Mar → 10 Oct = 223 days = 31×7 + 6
        $this->assertSame('G2P1+0', $p->obstetricFormula());
        $this->assertTrue($p->isHighRisk());

        // Scan EDD overrides LMP.
        $p->forceFill(['status' => 'ended'])->save();
        $scan = $this->book(['lmp' => null, 'edd_scan' => '2026-12-20']);
        $this->assertTrue($scan->edd_by_scan);
        $this->assertSame('29w 6d', $scan->gestationLabel());     // 280 − (10 Oct → 20 Dec = 71) = 209 days
    }

    public function test_booking_rules(): void
    {
        $this->book();
        $this->actingAs($this->midwife)->post(route('maternity.store', $this->mother), ['lmp' => '2026-03-01', 'gravida' => 2, 'parity' => 1, 'abortions' => 0, 'living_children' => 1])
            ->assertSessionHasErrors('patient'); // already has an active pregnancy

        $man = Patient::factory()->create(['gender' => 'male']);
        $this->actingAs($this->midwife)->post(route('maternity.store', $man), ['lmp' => '2026-03-01', 'gravida' => 1, 'parity' => 0, 'abortions' => 0, 'living_children' => 0])
            ->assertSessionHasErrors('patient');

        $other = Patient::factory()->create(['gender' => 'female']);
        $this->actingAs($this->midwife)->post(route('maternity.store', $other), ['gravida' => 1, 'parity' => 1, 'abortions' => 0, 'living_children' => 0])
            ->assertSessionHasErrors(['lmp', 'parity']);
    }

    public function test_anc_visit_flags_danger_signs(): void
    {
        $p = $this->book();

        $this->actingAs($this->midwife)->post(route('maternity.anc', $p), [
            'visit_date' => '2026-10-10', 'systolic' => 150, 'diastolic' => 100, 'urine_protein' => '++',
            'fundal_height' => 24, 'fetal_heart_rate' => 170, 'haemoglobin' => 9.5, 'interventions' => ['iptp', 'td'],
            'next_visit' => '2026-10-24',
        ])->assertSessionHas('warning');

        $visit = AncVisit::sole();
        $alerts = implode(' | ', $visit->alerts());
        $this->assertStringContainsString('pre-eclampsia', $alerts);
        $this->assertStringContainsString('Abnormal fetal heart rate', $alerts);
        $this->assertStringContainsString('does not match 31 weeks', $alerts);
        $this->assertStringContainsString('Anaemia', $alerts);
        $this->assertTrue($p->fresh()->isHighRisk());

        $this->actingAs($this->midwife)->get(route('maternity.show', $p))->assertOk()->assertSee('pre-eclampsia');
        $this->actingAs($this->midwife)->get(route('maternity.index'))->assertOk()->assertSee('High risk');
    }

    public function test_partograph_and_alert_line(): void
    {
        $p = $this->book();
        $this->actingAs($this->midwife)->post(route('maternity.labour', $p))->assertRedirect(route('maternity.partograph', $p));

        $this->actingAs($this->midwife)->post(route('maternity.partograph.store', $p), ['cervical_dilation' => 4, 'fetal_heart_rate' => 140]);
        Carbon::setTestNow('2026-10-10 14:00:00');
        $this->actingAs($this->midwife)->post(route('maternity.partograph.store', $p), ['cervical_dilation' => 6, 'fetal_heart_rate' => 172, 'liquor' => 'M'])
            ->assertSessionHas('warning');

        $this->actingAs($this->midwife)->get(route('maternity.partograph', $p))
            ->assertOk()->assertSee('Alert line')->assertSee('Action line')->assertSee('Meconium');
        $this->actingAs($this->midwife)->get(route('maternity.index', ['tab' => 'labour']))->assertSee($this->mother->list_name);
    }

    public function test_delivery_registers_babies_and_closes_pregnancy(): void
    {
        $del = Service::where('code', Service::DELIVERY_VAGINAL)->first();
        Price::create(['billable_type' => $del->getMorphClass(), 'billable_id' => $del->id, 'amount' => 25000]);
        $p = $this->book();

        $this->actingAs($this->midwife)->post(route('maternity.delivery.store', $p), [
            'delivered_at' => '2026-10-10T09:30', 'mode' => 'svd', 'blood_loss_ml' => 600, 'placenta_complete' => 1,
            'perineum' => 'tear1', 'maternal_outcome' => 'alive',
            'babies' => [
                ['sex' => 'female', 'outcome' => 'live_birth', 'birth_weight_g' => 2300, 'apgar_1' => 7, 'apgar_5' => 9, 'name' => 'Amina'],
                ['sex' => 'male', 'outcome' => 'fresh_stillbirth', 'birth_weight_g' => 2100],
            ],
        ])->assertSessionHasNoErrors();

        $delivery = Delivery::with('babies')->sole();
        $this->assertContains('pph', $delivery->complications); // auto-flagged from blood loss
        $this->assertSame(31, $delivery->gestation_weeks);

        $baby = $delivery->babies->firstWhere('outcome', 'live_birth')->patient;
        $this->assertSame('Amina', $baby->first_name);
        $this->assertSame('Bello', $baby->last_name);
        $this->assertSame($this->mother->id, $baby->mother_id);
        $this->assertSame('2026-10-10', $baby->date_of_birth->toDateString());
        $this->assertNull($delivery->babies->firstWhere('outcome', 'fresh_stillbirth')->patient_id); // stillbirths aren't registered

        $p->refresh();
        $this->assertSame('delivered', $p->status);
        $this->assertSame(2, $p->parity);
        $this->assertSame(2, $p->living_children);
        $this->assertSame(25000.0, (float) BillItem::where('description', 'Vaginal delivery')->value('amount'));

        $this->actingAs($this->midwife)->get(route('patients.show', $this->mother))->assertOk()->assertSee('Child: Baby Amina Bello');
        $this->actingAs($this->midwife)->get(route('patients.show', $baby))->assertOk()->assertSee('Mother:')->assertSee('Immunizations');

        // Postnatal visit after delivery.
        $this->actingAs($this->midwife)->post(route('maternity.postnatal', $p), [
            'visit_date' => '2026-10-10', 'uterus' => 'Well contracted', 'breastfeeding' => 'Exclusive', 'jaundice' => 1, 'mood_concern' => 0,
        ])->assertSessionHas('success');
        $this->actingAs($this->midwife)->get(route('maternity.show', $p))->assertOk()->assertSee('jaundice')->assertSee('LBW');
    }

    public function test_closing_a_pregnancy(): void
    {
        $p = $this->book();
        $this->actingAs($this->midwife)->post(route('maternity.end', $p), ['end_reason' => 'Miscarriage'])->assertSessionHas('success');
        $this->assertSame('ended', $p->fresh()->status);
        $this->actingAs($this->midwife)->post(route('maternity.anc', $p), ['visit_date' => '2026-10-10'])->assertSessionHasErrors('status');
    }

    public function test_immunization_schedule_recording_and_defaulters(): void
    {
        $child = Patient::factory()->create(['date_of_birth' => '2026-06-20', 'mother_id' => $this->mother->id]); // ~16 weeks old
        $service = app(ImmunizationService::class);

        $status = $service->schedule($child)->mapWithKeys(fn ($r) => [$r['vaccine']->code => $r['status']]);
        $this->assertSame('overdue', $status['BCG']);      // due at birth
        $this->assertSame('overdue', $status['PENTA1']);   // 6 weeks = 1 Aug, 70 days late
        $this->assertSame('due', $status['PENTA3']);       // 14 weeks = 26 Sep, 14 days late (within 28)
        $this->assertSame('upcoming', $status['MR1']);     // 9 months

        $this->assertTrue($service->defaulters()->contains(fn ($r) => $r['patient']->is($child)));

        $bcg = Vaccine::where('code', 'BCG')->first();
        $this->actingAs($this->midwife)->post(route('immunizations.store', $child), [
            'vaccine_id' => $bcg->id, 'given_on' => '2026-10-10', 'batch_number' => 'B123', 'site' => 'Left arm',
        ])->assertSessionHas('success');
        $this->assertSame('given', $service->schedule($child->fresh())->firstWhere('vaccine.code', 'BCG')['status']);

        // No double-recording, no date before birth.
        $this->actingAs($this->midwife)->post(route('immunizations.store', $child), ['vaccine_id' => $bcg->id, 'given_on' => '2026-10-10'])
            ->assertSessionHasErrors('vaccine_id');
        $this->actingAs($this->midwife)->post(route('immunizations.store', $child), [
            'vaccine_id' => Vaccine::where('code', 'OPV0')->value('id'), 'given_on' => '2026-05-01',
        ])->assertSessionHasErrors('given_on');

        $this->actingAs($this->midwife)->get(route('immunizations.index'))->assertOk()->assertSee($child->hospital_number);
        $this->actingAs($this->midwife)->get(route('immunizations.show', $child))->assertOk()->assertSee('Overdue');
        $this->actingAs($this->midwife)->get(route('immunizations.card', $child))->assertOk()->assertSee('VACCINATION CARD')->assertSee('B123');
    }

    public function test_permissions_catalogue_and_reports(): void
    {
        $p = $this->book();

        $cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($cashier)->get(route('maternity.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('immunizations.show', $this->mother))->assertOk(); // viewing needs only patient access
        $this->actingAs($cashier)->post(route('immunizations.store', $this->mother), [])->assertForbidden();

        $nurse = User::factory()->create()->assignRole('Nurse');
        $this->actingAs($nurse)->get(route('maternity.show', $p))->assertOk();
        $this->actingAs($nurse)->post(route('maternity.anc', $p), ['visit_date' => '2026-10-10'])->assertForbidden();

        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->get(route('admin.catalogs.index', 'vaccines'))->assertOk()->assertSee('Pentavalent');
        $this->actingAs($admin)->post(route('admin.catalogs.store', 'vaccines'), [
            'code' => 'hpv1', 'name' => 'HPV', 'dose_label' => 'Dose 1', 'age_days' => 3285, 'route' => 'IM', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame('9 years', Vaccine::where('code', 'HPV1')->first()->ageLabel());

        $this->actingAs($admin)->get(route('reports.show', ['report' => 'maternity', 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertSee('ANC bookings');
        $this->actingAs($admin)->get(route('reports.show', ['report' => 'immunizations', 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk();
        $this->actingAs($admin)->get(route('maternity.create', $this->mother))->assertOk();
        $this->actingAs($admin)->get(route('maternity.delivery', $p))->assertOk();
    }
}
