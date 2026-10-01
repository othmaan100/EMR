<?php

namespace App\Http\Controllers;

use App\Models\Diagnosis;
use App\Models\LabOrder;
use App\Services\LabService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LaboratoryController extends Controller
{
    public const TABS = [
        'collect' => ['label' => 'To collect', 'statuses' => ['requested'], 'icon' => 'bi-droplet'],
        'bench' => ['label' => 'In the lab', 'statuses' => ['collected'], 'icon' => 'bi-eyedropper'],
        'verify' => ['label' => 'To verify', 'statuses' => ['in_progress'], 'icon' => 'bi-patch-question'],
        'done' => ['label' => 'Released today', 'statuses' => ['completed'], 'icon' => 'bi-patch-check'],
    ];

    public function __construct(protected LabService $lab) {}

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'collect';
        $q = trim((string) $request->query('q'));

        $scope = fn ($query, string $key) => $query->whereIn('status', self::TABS[$key]['statuses'])
            ->when($key === 'done', fn ($q) => $q->whereDate('completed_at', today()));

        $orders = LabOrder::with(['patient', 'items.test', 'orderedBy'])
            ->tap(fn ($query) => $scope($query, $tab))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('order_number', $q)
                ->orWhereHas('patient', fn ($p) => $p->search($q))))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 ELSE 1 END")
            ->orderBy($tab === 'done' ? 'completed_at' : 'created_at', $tab === 'done' ? 'desc' : 'asc')
            ->paginate(30)->withQueryString();

        $counts = collect(self::TABS)->map(fn ($t, $key) => LabOrder::query()->tap(fn ($query) => $scope($query, $key))->count());

        return view('laboratory.index', compact('orders', 'tab', 'counts', 'q'));
    }

    public function show(LabOrder $labOrder): View
    {
        $labOrder->load(['patient', 'items.test.parameters', 'items.results', 'items.enteredBy', 'items.verifiedBy', 'orderedBy', 'collectedBy', 'visit.clinic']);

        return view('laboratory.show', ['order' => $labOrder, 'patient' => $labOrder->patient, 'visit' => $labOrder->visit]);
    }

    public function collect(Request $request, LabOrder $labOrder): RedirectResponse
    {
        $this->lab->collect($labOrder, $request->user());

        return redirect()->route('lab.show', $labOrder)
            ->with('success', "Sample collected for {$labOrder->order_number}. Print the label and send it to the bench.");
    }

    public function reject(Request $request, LabOrder $labOrder): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->lab->reject($labOrder, $request->user(), $data['reason']);

        return redirect()->route('lab.index')->with('warning', "Sample for {$labOrder->order_number} rejected and returned for recollection.");
    }

    public function saveResults(Request $request, LabOrder $labOrder): RedirectResponse
    {
        $request->validate([
            'results' => ['array'],
            'results.*' => ['array'],
            'results.*.*' => ['nullable', 'string', 'max:2000'],
            'comments' => ['array'],
            'comments.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $saved = $this->lab->saveResults($labOrder, $request->user(), $request->input('results', []), $request->input('comments', []));

        $message = $labOrder->status === 'in_progress'
            ? 'Results saved. The order is now waiting for verification.'
            : "Results saved for {$saved} test(s). Some tests are still pending.";

        return redirect()->route('lab.show', $labOrder)->with('success', $message);
    }

    public function verify(Request $request, LabOrder $labOrder): RedirectResponse
    {
        $this->lab->verify($labOrder, $request->user());

        return redirect()->route('lab.index', ['tab' => 'verify'])
            ->with('success', "{$labOrder->order_number} verified. Results are now visible to clinicians.");
    }

    public function label(LabOrder $labOrder): View
    {
        return view('laboratory.label', ['order' => $labOrder->load(['patient', 'items.test'])]);
    }

    /**
     * Printable report. Clinicians only see released results; lab staff can
     * preview unverified results (clearly marked).
     */
    public function report(Request $request, LabOrder $labOrder): View
    {
        $user = $request->user();
        abort_unless($user->can('lab.process') || ($user->can('lab.results.view') && $labOrder->isReleased()), 403,
            'Results are not available until the laboratory has verified them.');

        return view('laboratory.report', [
            'order' => $labOrder->load(['patient', 'items.test', 'items.results', 'items.enteredBy', 'items.verifiedBy', 'orderedBy', 'collectedBy', 'visit.clinic']),
            'diagnoses' => $labOrder->consultation_id
                ? Diagnosis::where('consultation_id', $labOrder->consultation_id)->orderByDesc('is_primary')->get()
                : collect(),
        ]);
    }
}
