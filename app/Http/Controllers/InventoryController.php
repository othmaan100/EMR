<?php

namespace App\Http\Controllers;

use App\Models\Drug;
use App\Models\StockBatch;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Services\PharmacyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(protected PharmacyService $pharmacy) {}

    public function index(Request $request): View
    {
        $filter = $request->query('filter');
        $q = trim((string) $request->query('q'));

        $drugs = Drug::withStock()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('form', 'like', "%$q%")))
            ->when($filter === 'low', fn ($query) => $query->lowStock())
            ->when($filter === 'expiring', fn ($query) => $query->whereHas('batches', fn ($b) => $b->where('quantity_on_hand', '>', 0)
                ->whereDate('expiry_date', '<=', today()->addDays(Drug::EXPIRY_WARNING_DAYS))))
            ->orderBy('name')->orderBy('strength')
            ->paginate(50)->withQueryString();

        return view('inventory.index', [
            'drugs' => $drugs,
            'filter' => $filter,
            'q' => $q,
            'lowCount' => $this->lowStockCount(),
            'expiringCount' => StockBatch::where('quantity_on_hand', '>', 0)->whereDate('expiry_date', '<=', today()->addDays(Drug::EXPIRY_WARNING_DAYS))->count(),
        ]);
    }

    public function show(Drug $drug): View
    {
        return view('inventory.show', [
            'drug' => $drug,
            'stock' => $drug->usableStock(),
            'batches' => $drug->batches()->with('receipt.supplier')->where('quantity_on_hand', '>', 0)->orderBy('expiry_date')->get(),
            'movements' => $drug->movements()->with(['batch', 'user'])->latest('id')->paginate(30),
        ]);
    }

    public function receiveForm(): View
    {
        return view('inventory.receive', [
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'drugs' => Drug::active()->orderBy('name')->get(),
            'recent' => StockReceipt::with(['supplier', 'receiver'])->withCount('batches')->latest('id')->limit(10)->get(),
        ]);
    }

    public function receive(Request $request): RedirectResponse
    {
        // Ignore completely empty rows from the dynamic form.
        $request->merge(['lines' => collect($request->input('lines', []))->filter(fn ($l) => filled($l['drug_id'] ?? null))->values()->all()]);

        $data = $request->validate([
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.drug_id' => ['required', Rule::exists('drugs', 'id')],
            'lines.*.batch_number' => ['required', 'string', 'max:50'],
            'lines.*.expiry_date' => ['required', 'date', 'after:today'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ], [
            'lines.required' => 'Add at least one item.',
            'lines.*.expiry_date.after' => 'Expired or same-day-expiry stock cannot be received.',
        ]);

        $receipt = $this->pharmacy->receive(
            collect($data)->only(['supplier_id', 'invoice_number', 'received_on', 'notes'])->all(),
            $data['lines'],
            $request->user()
        );

        return redirect()->route('inventory.receive')
            ->with('success', "Stock received on {$receipt->receipt_number} (".count($data['lines']).' line(s)).');
    }

    public function adjust(Request $request, StockBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['add', 'remove'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', Rule::in(['Stock count correction', 'Damaged / broken', 'Expired – disposed', 'Returned to supplier', 'Found / returned to stock'])],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $change = $data['direction'] === 'add' ? $data['quantity'] : -$data['quantity'];
        $type = str_starts_with($data['reason'], 'Expired') || str_starts_with($data['reason'], 'Damaged') ? 'disposal' : 'adjustment';
        $reason = $data['reason'].(! empty($data['note']) ? ': '.$data['note'] : '');

        $this->pharmacy->adjust($batch, $change, $type, $reason, $request->user());

        return back()->with('success', 'Stock adjusted.');
    }

    public static function lowStockCount(): int
    {
        return Drug::lowStock()->count();
    }
}
