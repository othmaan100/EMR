<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Services\BillingService;
use App\Services\PharmacyService;
use App\Support\AllergyChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyController extends Controller
{
    public const TABS = [
        'pending' => ['label' => 'To dispense', 'icon' => 'bi-hourglass-split'],
        'partial' => ['label' => 'Partially dispensed', 'icon' => 'bi-circle-half'],
        'done' => ['label' => 'Dispensed today', 'icon' => 'bi-check2-circle'],
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'pending';
        $q = trim((string) $request->query('q'));

        $scope = fn ($query, string $key) => match ($key) {
            'pending' => $query->where('status', 'pending'),
            'partial' => $query->where('status', 'partially_dispensed'),
            'done' => $query->where('status', 'dispensed')->whereDate('dispensed_at', today()),
        };

        $prescriptions = Prescription::with(['patient', 'items', 'prescriber', 'visit.clinic'])
            ->tap(fn ($query) => $scope($query, $tab))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('prescription_number', $q)
                ->orWhereHas('patient', fn ($p) => $p->search($q))))
            ->orderBy($tab === 'done' ? 'dispensed_at' : 'created_at', $tab === 'done' ? 'desc' : 'asc')
            ->paginate(30)->withQueryString();

        $counts = collect(self::TABS)->map(fn ($t, $key) => Prescription::query()->tap(fn ($query) => $scope($query, $key))->count());

        return view('pharmacy.index', compact('prescriptions', 'tab', 'counts', 'q'));
    }

    public function show(Prescription $prescription, BillingService $billing): View
    {
        $prescription->load(['patient', 'items.drug', 'prescriber', 'dispenser', 'visit.clinic']);
        $patient = $prescription->patient;

        return view('pharmacy.show', [
            'prescription' => $prescription,
            'patient' => $patient,
            'visit' => $prescription->visit,
            'stock' => $prescription->items->mapWithKeys(fn (PrescriptionItem $i) => [$i->id => $i->drug?->usableStock()]),
            'conflicts' => $prescription->items->mapWithKeys(fn (PrescriptionItem $i) => [$i->id => AllergyChecker::conflicts($patient, $i->drug_name)]),
            'payFirst' => (bool) setting('pharmacy_pay_first'),
            'due' => $prescription->items->mapWithKeys(fn (PrescriptionItem $i) => [$i->id => $i->pricedQuantity() > 0 ? $billing->outstandingFor(collect([$i])) : 0.0]),
        ]);
    }

    /**
     * Pay-first: charge the entered quantities and send the patient to the cashier.
     */
    public function price(Request $request, Prescription $prescription, PharmacyService $pharmacy): RedirectResponse
    {
        $data = $this->validateLines($request);
        $count = $pharmacy->price($prescription, $data['lines'], $request->user());

        return back()->with('success', $count
            ? "{$count} item(s) priced. Send the patient to the cashier, then dispense once paid."
            : 'Nothing new to price.');
    }

    public function dispense(Request $request, Prescription $prescription, PharmacyService $pharmacy): RedirectResponse
    {
        $data = $this->validateLines($request);

        $count = $pharmacy->dispense($prescription, $data['lines'], $request->user(), $request->boolean('allergy_confirmed'));
        $prescription->refresh();

        return redirect()->route('pharmacy.index', ['tab' => $prescription->status === 'dispensed' ? 'pending' : 'partial'])
            ->with('success', "{$prescription->prescription_number}: {$count} item(s) dispensed — {$prescription->statusLabel()}.");
    }

    protected function validateLines(Request $request): array
    {
        return $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'lines.*.unavailable' => ['nullable', 'string', 'max:255'],
            'allergy_confirmed' => ['boolean'],
        ]);
    }
}
