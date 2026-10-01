<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Accounts payable: what the hospital owes suppliers.
 */
class SupplierInvoiceController extends Controller
{
    public function __construct(protected ProcurementService $procurement) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['unpaid', 'overdue', 'paid'], true) ? $request->query('tab') : 'unpaid';

        $invoices = SupplierInvoice::with(['supplier', 'purchaseOrder.items', 'recorder'])
            ->when($tab === 'unpaid', fn ($q) => $q->where('status', 'unpaid')->orderByRaw('due_date is null')->orderBy('due_date'))
            ->when($tab === 'overdue', fn ($q) => $q->where('status', 'unpaid')->whereDate('due_date', '<', today())->orderBy('due_date'))
            ->when($tab === 'paid', fn ($q) => $q->where('status', 'paid')->latest('paid_on'))
            ->paginate(30)->withQueryString();

        return view('purchasing.invoices', [
            'invoices' => $invoices,
            'tab' => $tab,
            'mismatch' => $invoices->getCollection()->mapWithKeys(fn ($i) => [$i->id => $this->procurement->invoiceMismatch($i)]),
            'owed' => (float) SupplierInvoice::where('status', 'unpaid')->sum('amount'),
            'overdue' => (float) SupplierInvoice::where('status', 'unpaid')->whereDate('due_date', '<', today())->sum('amount'),
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'orders' => PurchaseOrder::whereIn('status', ['approved', 'partially_received', 'received'])->latest('id')->limit(100)->get(['id', 'po_number', 'supplier_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')],
            'purchase_order_id' => ['nullable', Rule::exists('purchase_orders', 'id')],
            'invoice_number' => ['required', 'string', 'max:50', Rule::unique('supplier_invoices')->where('supplier_id', $request->input('supplier_id'))],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['invoice_number.unique' => 'This invoice number is already recorded for this supplier.']);

        $invoice = $this->procurement->recordInvoice($data, $request->user());
        $warning = $this->procurement->invoiceMismatch($invoice);

        return back()->with($warning ? 'warning' : 'success', $warning ? "Invoice saved. Check before paying: {$warning}" : 'Invoice recorded.');
    }

    public function pay(Request $request, SupplierInvoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_reference' => ['required', 'string', 'max:100'],
        ]);

        $this->procurement->payInvoice($invoice, $data['paid_on'], $data['payment_reference'], $request->user());

        return back()->with('success', "Invoice {$invoice->invoice_number} marked paid.");
    }
}
