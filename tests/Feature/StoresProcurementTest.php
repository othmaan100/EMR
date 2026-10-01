<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Drug;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\StockBatch;
use App\Models\StoreItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\StoresService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoresProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected User $storekeeper;

    protected User $nurse;

    protected User $accountant;

    protected Department $ward;

    protected StoreItem $gloves;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();

        $this->ward = Department::create(['name' => 'Female Medical Ward', 'code' => 'FMW', 'type' => 'ward']);
        $this->storekeeper = User::factory()->create()->assignRole('Storekeeper');
        $this->nurse = User::factory()->create(['department_id' => $this->ward->id])->assignRole('Nurse');
        $this->accountant = User::factory()->create()->assignRole('Accountant');
        $this->supplier = Supplier::create(['name' => 'Medlink Supplies', 'is_active' => true]);

        $this->actingAs($this->storekeeper)->post(route('admin.catalogs.store', 'store-items'), [
            'code' => 'glv-m', 'name' => 'Examination gloves (M)', 'category' => 'Medical consumables', 'unit' => 'box', 'reorder_level' => 10, 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->gloves = StoreItem::where('code', 'GLV-M')->sole();
    }

    public function test_receipts_update_average_cost_and_ledger(): void
    {
        $this->actingAs($this->storekeeper)->post(route('stores.receive.store'), [
            'source' => 'Opening balance', 'lines' => [['store_item_id' => $this->gloves->id, 'quantity' => 10, 'unit_cost' => 1000]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->storekeeper)->post(route('stores.receive.store'), [
            'source' => 'Petty cash', 'lines' => [['store_item_id' => $this->gloves->id, 'quantity' => 30, 'unit_cost' => 1400]],
        ])->assertSessionHasNoErrors();

        $this->gloves->refresh();
        $this->assertSame(40, $this->gloves->quantity_on_hand);
        $this->assertEquals(1300, $this->gloves->average_cost); // (10×1000 + 30×1400) / 40
        $this->assertEquals(52000, $this->gloves->value());

        // Donation without cost keeps the average.
        app(StoresService::class)->receive($this->gloves, 10, null, $this->storekeeper, null, 'Donation');
        $this->assertEquals(1300, $this->gloves->fresh()->average_cost);

        // Write-off cannot go below zero; adjustments are logged.
        $this->actingAs($this->storekeeper)->post(route('stores.adjust', $this->gloves), ['type' => 'write_off', 'quantity' => 500, 'reason' => 'Damaged'])
            ->assertSessionHasErrors('quantity');
        $this->actingAs($this->storekeeper)->post(route('stores.adjust', $this->gloves), ['type' => 'write_off', 'quantity' => 5, 'reason' => 'Water damage'])
            ->assertSessionHasNoErrors();
        $this->assertSame(45, $this->gloves->fresh()->quantity_on_hand);

        $this->actingAs($this->storekeeper)->get(route('stores.show', $this->gloves))->assertOk()->assertSee('Water damage')->assertSee('Opening balance');
        $this->actingAs($this->storekeeper)->get(route('stores.index'))->assertOk()->assertSee('Examination gloves (M)');
    }

    public function test_requisition_partial_issue_and_close(): void
    {
        app(StoresService::class)->receive($this->gloves, 8, 1000, $this->storekeeper);

        $this->actingAs($this->nurse)->get(route('requisitions.create'))->assertOk();
        $this->actingAs($this->nurse)->post(route('requisitions.store'), [
            'department_id' => $this->ward->id, 'lines' => [['store_item_id' => $this->gloves->id, 'quantity' => 12]],
        ])->assertSessionHasNoErrors();
        $req = Requisition::sole();
        $line = $req->items()->sole();

        // The nurse cannot issue; the store cannot issue more than it holds.
        $this->actingAs($this->nurse)->post(route('requisitions.issue', $req), ['issue' => [$line->id => 5]])->assertForbidden();
        $this->actingAs($this->storekeeper)->post(route('requisitions.issue', $req), ['issue' => [$line->id => 10]])->assertSessionHasErrors("issue.{$line->id}");

        $this->actingAs($this->storekeeper)->post(route('requisitions.issue', $req), ['issue' => [$line->id => 8]])->assertSessionHasNoErrors();
        $this->assertSame('partially_issued', $req->fresh()->status);
        $this->assertSame(0, $this->gloves->fresh()->quantity_on_hand);
        $this->assertTrue($this->gloves->fresh()->isLow());

        $this->actingAs($this->nurse)->get(route('requisitions.show', $req))->assertOk()->assertSee('Partly issued');
        $this->actingAs($this->storekeeper)->post(route('requisitions.reject', $req), ['reason' => 'Out of stock — reordered'])->assertSessionHasNoErrors();
        $this->assertSame('issued', $req->fresh()->status); // what was given stands

        // Consumption report values the issue at average cost.
        $this->actingAs($this->storekeeper)->get(route('reports.show', ['report' => 'stores']))->assertOk()->assertSee('Female Medical Ward');

        // Another department's nurse cannot open it.
        $other = User::factory()->create()->assignRole('Nurse');
        $this->actingAs($other)->get(route('requisitions.show', $req))->assertForbidden();
    }

    public function test_purchase_order_approval_receiving_store_and_drug_lines(): void
    {
        $this->seed(CatalogSeeder::class);
        $drug = Drug::where('name', 'Paracetamol')->where('form', 'Tablet')->firstOrFail();

        $this->actingAs($this->storekeeper)->post(route('purchasing.store'), [
            'supplier_id' => $this->supplier->id, 'order_date' => today()->toDateString(),
            'lines' => [
                ['item' => "store:{$this->gloves->id}", 'quantity' => 20, 'unit_price' => 1500],
                ['item' => "drug:{$drug->id}", 'quantity' => 1000, 'unit_price' => 5],
            ],
        ])->assertSessionHasNoErrors();
        $po = PurchaseOrder::sole();
        $this->assertSame('draft', $po->status);
        $this->assertEquals(35000, $po->total);

        // Cannot receive a draft; the person who raised it cannot approve it.
        $this->actingAs($this->storekeeper)->post(route('purchasing.approve', $po))->assertForbidden();
        $this->actingAs($this->accountant)->post(route('purchasing.approve', $po))->assertSessionHasNoErrors();
        $this->assertSame('approved', $po->fresh()->status);
        $this->actingAs($this->accountant)->get(route('purchasing.print', $po))->assertOk()->assertSee('PURCHASE ORDER')->assertSee('Medlink Supplies');

        [$storeLine, $drugLine] = $po->items()->get()->all();

        // Drug lines need batch & expiry.
        $this->actingAs($this->storekeeper)->post(route('purchasing.receive', $po), [
            'received_on' => today()->toDateString(), 'lines' => [$drugLine->id => ['quantity' => 500]],
        ])->assertSessionHasErrors("lines.{$drugLine->id}.batch_number");

        $this->actingAs($this->storekeeper)->post(route('purchasing.receive', $po), [
            'received_on' => today()->toDateString(), 'delivery_note' => 'WB-17',
            'lines' => [
                $storeLine->id => ['quantity' => 20],
                $drugLine->id => ['quantity' => 600, 'batch_number' => 'PCM-9', 'expiry_date' => now()->addYear()->toDateString()],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('partially_received', $po->fresh()->status);
        $this->assertSame(20, $this->gloves->fresh()->quantity_on_hand);
        $this->assertEquals(1500, $this->gloves->fresh()->average_cost);
        $this->assertSame(600, StockBatch::where('drug_id', $drug->id)->where('batch_number', 'PCM-9')->sole()->quantity_on_hand);

        // Over-delivery refused; then close short.
        $this->actingAs($this->storekeeper)->post(route('purchasing.receive', $po), [
            'received_on' => today()->toDateString(), 'lines' => [$drugLine->id => ['quantity' => 900, 'batch_number' => 'X', 'expiry_date' => now()->addYear()->toDateString()]],
        ])->assertSessionHasErrors("lines.{$drugLine->id}.quantity");
        $this->actingAs($this->storekeeper)->post(route('purchasing.close', $po), ['reason' => 'Supplier out of stock'])->assertSessionHasNoErrors();
        $this->assertSame('received', $po->fresh()->status);
    }

    public function test_supplier_invoice_matching_and_segregated_payment(): void
    {
        $po = new PurchaseOrder(['supplier_id' => $this->supplier->id, 'order_date' => today()]);
        $po->forceFill(['po_number' => 'PO-1', 'status' => 'received', 'total' => 10000, 'created_by' => $this->storekeeper->id])->save();
        $line = $po->items()->make(['description' => 'Gloves', 'quantity' => 10, 'unit_price' => 1000]);
        $line->item()->associate($this->gloves);
        $line->quantity_received = 8;
        $line->save();

        $this->actingAs($this->accountant)->post(route('invoices.store'), [
            'supplier_id' => $this->supplier->id, 'purchase_order_id' => $po->id, 'invoice_number' => 'INV-55',
            'invoice_date' => today()->toDateString(), 'due_date' => today()->addDays(30)->toDateString(), 'amount' => 10000,
        ])->assertSessionHas('warning'); // 10,000 invoiced, 8,000 received

        $this->actingAs($this->accountant)->post(route('invoices.store'), [
            'supplier_id' => $this->supplier->id, 'invoice_number' => 'INV-55', 'invoice_date' => today()->toDateString(), 'amount' => 5,
        ])->assertSessionHasErrors('invoice_number');

        $invoice = SupplierInvoice::sole();
        $this->actingAs($this->accountant)->get(route('invoices.index'))->assertOk()->assertSee('INV-55')->assertSee('only');

        // Same person cannot enter and pay.
        $this->actingAs($this->accountant)->post(route('invoices.pay', $invoice), ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRF-1'])
            ->assertSessionHasErrors('status');
        $cfo = User::factory()->create()->assignRole('Accountant');
        $this->actingAs($cfo)->post(route('invoices.pay', $invoice), ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRF-1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->actingAs($cfo)->get(route('reports.show', ['report' => 'procurement']))->assertOk()->assertSee('Medlink Supplies');
    }

    public function test_raiser_cannot_approve_own_order(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->post(route('purchasing.store'), [
            'supplier_id' => $this->supplier->id, 'order_date' => today()->toDateString(),
            'lines' => [['item' => "store:{$this->gloves->id}", 'quantity' => 5, 'unit_price' => 100]],
        ])->assertSessionHasNoErrors();
        $po = PurchaseOrder::sole();

        $this->actingAs($admin)->post(route('purchasing.approve', $po))->assertSessionHasErrors('status');
        $this->assertSame('draft', $po->fresh()->status);
        $this->actingAs($this->accountant)->post(route('purchasing.approve', $po))->assertSessionHasNoErrors();
    }

    public function test_permissions(): void
    {
        $this->actingAs($this->nurse)->get(route('stores.index'))->assertForbidden();
        $this->actingAs($this->nurse)->get(route('purchasing.index'))->assertForbidden();
        $this->actingAs($this->storekeeper)->get(route('invoices.index'))->assertForbidden();
        $this->actingAs($this->storekeeper)->get(route('admin.catalogs.index', 'drugs'))->assertForbidden();
        $this->actingAs($this->storekeeper)->get(route('admin.catalogs.index', 'store-items'))->assertOk()->assertDontSee('Lab Tests');
        $this->actingAs($this->storekeeper)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($this->storekeeper)->get(route('dashboard'))->assertOk()->assertSee('Requisitions to issue');
    }
}
