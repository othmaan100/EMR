<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Visit;
use App\Services\QueueService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected User $pharmacist;

    protected Patient $patient;

    protected Drug $amox;

    protected Drug $pcm;

    protected Consultation $consultation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        $this->seed(CatalogSeeder::class);

        $this->doctor = User::factory()->create()->assignRole('Doctor');
        $this->pharmacist = User::factory()->create()->assignRole('Pharmacist');
        $this->patient = Patient::factory()->create(['allergies' => null]);
        $this->amox = Drug::where('name', 'Amoxicillin')->where('form', 'Capsule')->firstOrFail();
        $this->pcm = Drug::where('name', 'Paracetamol')->where('form', 'Tablet')->firstOrFail();

        $clinic = Clinic::factory()->create(['requires_triage' => false]);
        $visit = app(QueueService::class)->checkIn($this->patient, ['clinic_id' => $clinic->id], $this->doctor);
        $this->actingAs($this->doctor)->patch(route('visits.move', $visit), ['status' => Visit::IN_CONSULTATION]);
        $this->actingAs($this->doctor)->get(route('consultations.show', $visit));
        $this->consultation = Consultation::firstOrFail();
    }

    protected function prescribe(Drug|string $drug, ?int $qty): void
    {
        $this->actingAs($this->doctor)->post(route('consultations.prescribe', $this->consultation), [
            'drug' => $drug instanceof Drug ? $drug->label : $drug, 'dose' => '1', 'route' => 'Oral', 'frequency' => 'TDS',
            'duration_value' => 5, 'duration_unit' => 'days', 'quantity' => $qty,
        ])->assertSessionHasNoErrors();
    }

    protected function receive(Drug $drug, string $batch, string $expiry, int $qty): void
    {
        $this->actingAs($this->pharmacist)->post(route('inventory.receive.store'), [
            'received_on' => today()->toDateString(),
            'lines' => [['drug_id' => $drug->id, 'batch_number' => $batch, 'expiry_date' => $expiry, 'quantity' => $qty, 'unit_cost' => 10]],
        ])->assertSessionHasNoErrors();
    }

    public function test_receiving_stock_creates_batches_and_ledger(): void
    {
        $supplier = Supplier::create(['name' => 'MedSupply Ltd']);

        $this->actingAs($this->pharmacist)->post(route('inventory.receive.store'), [
            'supplier_id' => $supplier->id, 'invoice_number' => 'INV-9', 'received_on' => today()->toDateString(),
            'lines' => [
                ['drug_id' => $this->amox->id, 'batch_number' => 'A1', 'expiry_date' => now()->addYear()->toDateString(), 'quantity' => 100],
                ['drug_id' => $this->pcm->id, 'batch_number' => 'P1', 'expiry_date' => now()->addYear()->toDateString(), 'quantity' => 500],
                ['drug_id' => '', 'batch_number' => '', 'expiry_date' => '', 'quantity' => ''], // blank row ignored
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(100, $this->amox->usableStock());
        $this->assertSame(2, StockMovement::where('type', 'receipt')->count());
        $this->assertMatchesRegularExpression('/^GRN\d{4}-000001$/', \App\Models\StockReceipt::first()->receipt_number);

        // Expired stock can't be received.
        $this->actingAs($this->pharmacist)->post(route('inventory.receive.store'), [
            'received_on' => today()->toDateString(),
            'lines' => [['drug_id' => $this->amox->id, 'batch_number' => 'X', 'expiry_date' => now()->subDay()->toDateString(), 'quantity' => 5]],
        ])->assertSessionHasErrors('lines.0.expiry_date');
    }

    public function test_dispensing_uses_first_expiring_batch_and_skips_expired(): void
    {
        $this->receive($this->amox, 'LATE', now()->addYear()->toDateString(), 50);
        $this->receive($this->amox, 'SOON', now()->addMonth()->toDateString(), 10);
        // An expired batch already in the store must never be used.
        StockBatch::create(['drug_id' => $this->amox->id, 'batch_number' => 'OLD', 'expiry_date' => now()->subDay(), 'quantity_received' => 99, 'quantity_on_hand' => 99]);

        $this->prescribe($this->amox, 15);
        $rx = Prescription::with('items')->firstOrFail();

        $this->actingAs($this->pharmacist)->post(route('pharmacy.dispense', $rx), [
            'lines' => [$rx->items[0]->id => ['quantity' => 15]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, StockBatch::where('batch_number', 'SOON')->value('quantity_on_hand'));
        $this->assertSame(45, StockBatch::where('batch_number', 'LATE')->value('quantity_on_hand'));
        $this->assertSame(99, StockBatch::where('batch_number', 'OLD')->value('quantity_on_hand'));

        $rx->refresh();
        $this->assertSame('dispensed', $rx->status);
        $this->assertSame($this->pharmacist->id, $rx->dispensed_by);
        $this->assertSame(-15, (int) StockMovement::where('type', 'dispense')->sum('quantity'));
    }

    public function test_partial_dispensing_then_completion(): void
    {
        $this->receive($this->amox, 'A', now()->addYear()->toDateString(), 10);
        $this->prescribe($this->amox, 15);
        $this->prescribe($this->pcm, 10);
        $rx = Prescription::with('items')->firstOrFail();
        [$amoxItem, $pcmItem] = [$rx->items[0], $rx->items[1]];

        // Not enough paracetamol (none received): whole attempt is rejected, nothing moves.
        $this->actingAs($this->pharmacist)->post(route('pharmacy.dispense', $rx), [
            'lines' => [$amoxItem->id => ['quantity' => 10], $pcmItem->id => ['quantity' => 10]],
        ])->assertSessionHasErrors("lines.{$pcmItem->id}.quantity");
        $this->assertSame(10, $this->amox->usableStock());

        // Give what we have; paracetamol bought outside.
        $this->actingAs($this->pharmacist)->post(route('pharmacy.dispense', $rx), [
            'lines' => [$amoxItem->id => ['quantity' => 10], $pcmItem->id => ['unavailable' => 'Out of stock – patient to buy outside']],
        ])->assertSessionHasNoErrors();

        $rx->refresh();
        $this->assertSame('partially_dispensed', $rx->status);
        $this->assertSame(10, $amoxItem->fresh()->quantity_dispensed);

        // Can't over-dispense.
        $this->receive($this->amox, 'B', now()->addYear()->toDateString(), 20);
        $this->actingAs($this->pharmacist)->post(route('pharmacy.dispense', $rx), ['lines' => [$amoxItem->id => ['quantity' => 6]]])
            ->assertSessionHasErrors("lines.{$amoxItem->id}.quantity");

        $this->actingAs($this->pharmacist)->post(route('pharmacy.dispense', $rx), ['lines' => [$amoxItem->id => ['quantity' => 5]]])
            ->assertSessionHasNoErrors();
        $this->assertSame('dispensed', $rx->fresh()->status);

        // Finished prescriptions can't be dispensed again.
        $this->actingAs($this->pharmacist)->post(route('pharmacy.dispense', $rx), ['lines' => [$amoxItem->id => ['quantity' => 1]]])
            ->assertSessionHasErrors('status');
    }

    public function test_free_text_items_can_only_be_marked_unavailable(): void
    {
        $this->prescribe('Herbal Mixture X', null);
        $rx = Prescription::with('items')->firstOrFail();

        $this->actingAs($this->pharmacist)->post(route('pharmacy.dispense', $rx), ['lines' => [$rx->items[0]->id => ['quantity' => 1]]])
            ->assertSessionHasErrors("lines.{$rx->items[0]->id}.quantity");
    }

    public function test_pharmacist_must_confirm_allergy_conflicts(): void
    {
        $this->patient->update(['allergies' => 'Penicillin']);
        $this->receive($this->amox, 'A', now()->addYear()->toDateString(), 30);
        $this->actingAs($this->doctor)->post(route('consultations.prescribe', $this->consultation), [
            'drug' => $this->amox->label, 'dose' => '500mg', 'route' => 'Oral', 'frequency' => 'TDS',
            'duration_value' => 5, 'duration_unit' => 'days', 'quantity' => 15, 'allergy_override' => 1,
        ]);
        $rx = Prescription::with('items')->firstOrFail();

        $this->actingAs($this->pharmacist)->get(route('pharmacy.show', $rx))->assertOk()->assertSee('Allergy: penicillin');

        $this->actingAs($this->pharmacist)->post(route('pharmacy.dispense', $rx), ['lines' => [$rx->items[0]->id => ['quantity' => 15]]])
            ->assertSessionHasErrors('allergy');
        $this->assertSame(30, $this->amox->usableStock());

        $this->actingAs($this->pharmacist)->post(route('pharmacy.dispense', $rx), [
            'lines' => [$rx->items[0]->id => ['quantity' => 15]], 'allergy_confirmed' => 1,
        ])->assertSessionHasNoErrors();
    }

    public function test_adjustments_and_alerts(): void
    {
        $this->amox->update(['reorder_level' => 20]);
        $this->receive($this->amox, 'A', now()->addDays(30)->toDateString(), 25);
        $batch = StockBatch::firstOrFail();

        $this->actingAs($this->pharmacist)->post(route('inventory.adjust', $batch), [
            'direction' => 'remove', 'quantity' => 10, 'reason' => 'Damaged / broken',
        ])->assertSessionHas('success');
        $this->assertSame(15, $batch->fresh()->quantity_on_hand);
        $this->assertSame('disposal', StockMovement::latest('id')->first()->type);

        $this->actingAs($this->pharmacist)->post(route('inventory.adjust', $batch), [
            'direction' => 'remove', 'quantity' => 100, 'reason' => 'Stock count correction',
        ])->assertSessionHasErrors('quantity');

        // Low (15 <= 20) and expiring (30 days) show on inventory and dashboard.
        $this->actingAs($this->pharmacist)->get(route('inventory.index', ['filter' => 'low']))->assertOk()->assertSee($this->amox->label);
        $this->actingAs($this->pharmacist)->get(route('inventory.index', ['filter' => 'expiring']))->assertOk()->assertSee($this->amox->label);
        $this->actingAs($this->pharmacist)->get(route('inventory.index', ['filter' => 'low']))->assertDontSee($this->pcm->label); // no reorder level set
        $this->actingAs($this->pharmacist)->get(route('dashboard'))->assertOk()->assertSee('Drugs at/below reorder level');
        $this->actingAs($this->pharmacist)->get(route('inventory.show', $this->amox))->assertOk()->assertSee('Expires soon')->assertSee('Damaged / broken');
    }

    public function test_pages_and_permissions(): void
    {
        $this->prescribe($this->pcm, 10);
        $rx = Prescription::firstOrFail();

        foreach (['pending', 'partial', 'done'] as $tab) {
            $this->actingAs($this->pharmacist)->get(route('pharmacy.index', ['tab' => $tab]))->assertOk();
        }
        $this->actingAs($this->pharmacist)->get(route('pharmacy.index'))->assertSee($rx->prescription_number);
        $this->actingAs($this->pharmacist)->get(route('pharmacy.show', $rx))->assertOk();
        $this->actingAs($this->pharmacist)->get(route('inventory.receive'))->assertOk();
        $this->actingAs($this->pharmacist)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($this->pharmacist)->get(route('suppliers.create'))->assertOk();
        $this->actingAs($this->pharmacist)->post(route('suppliers.store'), ['name' => 'Acme Pharma', 'is_active' => 1])->assertRedirect(route('suppliers.index'));

        // Doctors can see stock but not dispense or receive.
        $this->actingAs($this->doctor)->get(route('inventory.index'))->assertOk();
        $this->actingAs($this->doctor)->get(route('pharmacy.index'))->assertForbidden();
        $this->actingAs($this->doctor)->get(route('inventory.receive'))->assertForbidden();

        $nurse = User::factory()->create()->assignRole('Nurse');
        $this->actingAs($nurse)->get(route('inventory.index'))->assertForbidden();
    }
}
