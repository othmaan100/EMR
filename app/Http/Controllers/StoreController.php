<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\StoreItem;
use App\Models\StoreMovement;
use App\Services\StoresService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * General (non-drug) store: stock list, item ledger, direct receipts, adjustments.
 */
class StoreController extends Controller
{
    public function __construct(protected StoresService $stores) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(StoreItem::CATEGORIES)],
            'filter' => ['nullable', Rule::in(['low', 'out'])],
        ]);

        $items = StoreItem::active()
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('code', 'like', "%{$t}%")))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when(($filters['filter'] ?? null) === 'low', fn ($q) => $q->lowStock())
            ->when(($filters['filter'] ?? null) === 'out', fn ($q) => $q->where('quantity_on_hand', '<=', 0))
            ->orderBy('category')->orderBy('name')->paginate(50)->withQueryString();

        return view('stores.index', [
            'items' => $items,
            'filters' => $filters,
            'totalValue' => (float) StoreItem::active()->where('quantity_on_hand', '>', 0)->sum(DB::raw('quantity_on_hand * average_cost')),
            'lowCount' => StoreItem::lowStock()->count(),
            'openRequisitions' => Requisition::whereIn('status', Requisition::OPEN)->count(),
        ]);
    }

    public function show(StoreItem $item): View
    {
        return view('stores.show', [
            'item' => $item,
            'movements' => StoreMovement::with(['department', 'user', 'reference'])->where('store_item_id', $item->id)->latest('id')->paginate(50),
        ]);
    }

    public function receiveForm(): View
    {
        return view('stores.receive', ['items' => StoreItem::active()->orderBy('name')->get()]);
    }

    /**
     * Stock that arrives without a purchase order (donations, petty-cash buys).
     */
    public function receive(Request $request): RedirectResponse
    {
        $request->merge(['lines' => collect($request->input('lines', []))->filter(fn ($l) => filled($l['store_item_id'] ?? null))->values()->all()]);
        $data = $request->validate([
            'source' => ['required', 'string', 'max:150'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.store_item_id' => ['required', Rule::exists('store_items', 'id')->where('is_active', true)],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ], ['lines.required' => 'Add at least one item.']);

        DB::transaction(function () use ($data, $request) {
            foreach ($data['lines'] as $line) {
                $this->stores->receive(StoreItem::findOrFail($line['store_item_id']), (int) $line['quantity'],
                    isset($line['unit_cost']) && $line['unit_cost'] !== '' ? (float) $line['unit_cost'] : null, $request->user(), null, $data['source']);
            }
        });

        return redirect()->route('stores.index')->with('success', count($data['lines']).' item(s) received into the store.');
    }

    public function adjust(Request $request, StoreItem $item): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['adjustment', 'write_off', 'return'])],
            'quantity' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $change = $data['type'] === 'write_off' ? -abs($data['quantity']) : ($data['type'] === 'return' ? abs($data['quantity']) : (int) $data['quantity']);

        $this->stores->adjust($item, $change, $data['type'], $data['reason'], $request->user());

        return back()->with('success', 'Stock updated.');
    }
}
