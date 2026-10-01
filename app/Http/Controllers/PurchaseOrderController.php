<?php

namespace App\Http\Controllers;

use App\Models\Drug;
use App\Models\PurchaseOrder;
use App\Models\StoreItem;
use App\Models\Supplier;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(protected ProcurementService $procurement) {}

    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), PurchaseOrder::STATUSES) ? $request->query('status') : null;

        return view('purchasing.index', [
            'orders' => PurchaseOrder::with('supplier')->withCount('items')
                ->when($status, fn ($q, $s) => $q->where('status', $s))
                ->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => PurchaseOrder::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function create(Request $request): View
    {
        // Pre-fill from low-stock items when asked.
        $prefill = [];
        if ($request->boolean('low_stock')) {
            $prefill = StoreItem::lowStock()->orderBy('name')->get()
                ->map(fn ($i) => ['item' => "store:{$i->id}", 'quantity' => max(1, $i->reorder_level * 2 - $i->quantity_on_hand), 'unit_price' => $i->average_cost ?: null])->all();
        }

        return view('purchasing.create', [
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'options' => $this->itemOptions(),
            'prefill' => $prefill,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['lines' => collect($request->input('lines', []))->filter(fn ($l) => filled($l['item'] ?? null))->values()->all()]);
        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'order_date' => ['required', 'date', 'before_or_equal:today'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item' => ['required', 'regex:/^(store|drug):\d+$/'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ], ['lines.required' => 'Add at least one item.']);

        $po = $this->procurement->createOrder(collect($data)->only(['supplier_id', 'order_date', 'expected_date', 'notes'])->all(), $data['lines'], $request->user());

        return redirect()->route('purchasing.show', $po)->with('success', "{$po->po_number} saved as a draft. It needs approval before it is sent.");
    }

    public function show(PurchaseOrder $order): View
    {
        return view('purchasing.show', ['po' => $order->load(['supplier', 'items.item', 'creator', 'approver', 'invoices'])]);
    }

    public function print(PurchaseOrder $order): View
    {
        abort_if($order->status === 'draft', 404);

        return view('purchasing.print', ['po' => $order->load(['supplier', 'items', 'creator', 'approver'])]);
    }

    public function approve(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->procurement->approve($order, $request->user());

        return back()->with('success', "{$order->po_number} approved. Print it and send it to the supplier.");
    }

    public function cancel(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->procurement->cancel($order, $data['reason'], $request->user());

        return back()->with('success', "{$order->po_number} cancelled.");
    }

    public function receive(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'delivery_note' => ['nullable', 'string', 'max:50'],
            'lines' => ['required', 'array'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:50'],
            'lines.*.expiry_date' => ['nullable', 'date'],
        ]);

        $count = $this->procurement->receive($order, $data['lines'], $data['received_on'], $data['delivery_note'] ?? null, $request->user());

        return back()->with('success', "{$count} line(s) received into stock.");
    }

    public function closeShort(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->procurement->closeShort($order, $data['reason'], $request->user());

        return back()->with('success', "{$order->po_number} closed.");
    }

    /**
     * @return array<string, array<string, string>>  optgroup => [value => label]
     */
    protected function itemOptions(): array
    {
        return [
            'General store' => StoreItem::active()->orderBy('name')->get()->mapWithKeys(fn ($i) => ["store:{$i->id}" => $i->label])->all(),
            'Drugs (pharmacy)' => Drug::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($d) => ["drug:{$d->id}" => $d->label])->all(),
        ];
    }
}
