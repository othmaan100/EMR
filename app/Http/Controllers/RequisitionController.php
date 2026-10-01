<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Requisition;
use App\Models\StoreItem;
use App\Services\StoresService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Wards and departments request items; the store issues them.
 */
class RequisitionController extends Controller
{
    public function __construct(protected StoresService $stores) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $canStore = $user->can('stores.view');
        $tab = in_array($request->query('tab'), ['open', 'mine', 'all'], true) ? $request->query('tab') : ($canStore ? 'open' : 'mine');
        if (! $canStore) {
            $tab = 'mine';
        }

        $requisitions = Requisition::with(['department', 'requester', 'items'])
            ->when($tab === 'open', fn ($q) => $q->whereIn('status', Requisition::OPEN)->orderBy('needed_by')->orderBy('id'))
            ->when($tab === 'mine', fn ($q) => $q->where(fn ($w) => $w->where('requested_by', $user->id)
                ->when($user->department_id, fn ($d) => $d->orWhere('department_id', $user->department_id)))->latest('id'))
            ->when($tab === 'all', fn ($q) => $q->latest('id'))
            ->paginate(30)->withQueryString();

        return view('requisitions.index', compact('requisitions', 'tab', 'canStore'));
    }

    public function create(Request $request): View
    {
        return view('requisitions.create', [
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'items' => StoreItem::active()->orderBy('category')->orderBy('name')->get(),
            'defaultDepartment' => $request->user()->department_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['lines' => collect($request->input('lines', []))->filter(fn ($l) => filled($l['store_item_id'] ?? null))->values()->all()]);
        $data = $request->validate([
            'department_id' => ['required', Rule::exists('departments', 'id')],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.store_item_id' => ['required', Rule::exists('store_items', 'id')->where('is_active', true)],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ], ['lines.required' => 'Add at least one item.']);

        $req = $this->stores->requisition($data['department_id'], $data['lines'], $data['needed_by'] ?? null, $data['notes'] ?? null, $request->user());

        return redirect()->route('requisitions.show', $req)->with('success', "Requisition {$req->requisition_number} sent to the store.");
    }

    public function show(Request $request, Requisition $requisition): View
    {
        $user = $request->user();
        abort_unless($user->can('stores.view') || $requisition->requested_by === $user->id
            || ($user->department_id && $requisition->department_id === $user->department_id), 403);

        return view('requisitions.show', ['requisition' => $requisition->load(['department', 'requester', 'issuer', 'items.item'])]);
    }

    public function issue(Request $request, Requisition $requisition): RedirectResponse
    {
        $data = $request->validate(['issue' => ['required', 'array'], 'issue.*' => ['nullable', 'integer', 'min:0']]);
        $count = $this->stores->issue($requisition, array_map('intval', array_filter($data['issue'])), $request->user());

        return back()->with('success', "{$count} item(s) issued to {$requisition->department->name}.");
    }

    public function reject(Request $request, Requisition $requisition): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->stores->close($requisition, 'rejected', $data['reason'], $request->user());

        return back()->with('success', 'Requisition closed.');
    }

    public function cancel(Request $request, Requisition $requisition): RedirectResponse
    {
        abort_unless($requisition->requested_by === $request->user()->id, 403);
        $this->stores->close($requisition, 'cancelled', 'Cancelled by requester', $request->user());

        return back()->with('success', 'Requisition cancelled.');
    }
}
