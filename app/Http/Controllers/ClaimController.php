<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\ClaimBatch;
use App\Models\InsuranceProvider;
use App\Integrations\Claims\ElectronicClaimService;
use App\Services\ClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Insurance / HMO claims: batches, submission, remittance and claim forms.
 */
class ClaimController extends Controller
{
    public function __construct(protected ClaimService $claims) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['unbatched', 'batches', 'rejected'])],
            'provider_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(ClaimBatch::STATUSES))],
        ]);
        $tab = $filters['tab'] ?? 'unbatched';
        $providerId = $filters['provider_id'] ?? null;

        $data = [
            'tab' => $tab,
            'filters' => $filters,
            'providers' => InsuranceProvider::orderBy('name')->pluck('name', 'id'),
            'counts' => [
                'unbatched' => Bill::where('claim_status', 'pending')->whereNull('claim_batch_id')->count(),
                'batches' => ClaimBatch::where('status', '!=', 'reconciled')->count(),
                'rejected' => Bill::whereIn('claim_status', ['rejected', 'part_paid'])->whereNull('claim_transferred_at')->count(),
            ],
        ];

        if ($tab === 'unbatched') {
            $bills = $this->claims->unbatched($providerId)->oldest('id')->get()->filter(fn (Bill $b) => $b->totals()['insurance'] > 0);
            $data['groups'] = $bills->groupBy('insurance_provider_id');
            $data['blockers'] = $bills->mapWithKeys(fn (Bill $b) => [$b->id => $this->claims->blocker($b)]);
        } elseif ($tab === 'batches') {
            $data['batches'] = ClaimBatch::with('insuranceProvider')->withCount('bills')
                ->when($providerId, fn ($q, $id) => $q->where('insurance_provider_id', $id))
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->latest('id')->paginate(30)->withQueryString();
        } else {
            $data['bills'] = Bill::with(['patient', 'insuranceProvider', 'claimBatch'])
                ->whereIn('claim_status', ['rejected', 'part_paid'])->whereNull('claim_transferred_at')
                ->when($providerId, fn ($q, $id) => $q->where('insurance_provider_id', $id))
                ->oldest('claim_paid_at')->get();
        }

        return view('claims.index', $data);
    }

    public function storeBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'insurance_provider_id' => ['required', 'exists:insurance_providers,id'],
            'bills' => ['required', 'array', 'min:1'],
            'bills.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['bills.required' => 'Select at least one bill.']);

        $batch = $this->claims->createBatch(InsuranceProvider::findOrFail($data['insurance_provider_id']), $data['bills'], $request->user(), $data['notes'] ?? null);

        return redirect()->route('billing.claims.batch', $batch)->with('success', "Batch {$batch->batch_number} created. Check it, then submit.");
    }

    public function showBatch(ClaimBatch $batch): View
    {
        $batch->load(['insuranceProvider', 'creator', 'submitter', 'bills.patient', 'bills.items', 'bills.visit.clinic', 'bills.admission']);

        return view('claims.batch', ['batch' => $batch]);
    }

    public function submit(Request $request, ClaimBatch $batch): RedirectResponse
    {
        $this->claims->submit($batch, $request->user());

        return back()->with('success', "Batch {$batch->batch_number} marked as submitted to {$batch->insuranceProvider->name}.");
    }

    public function destroyBatch(ClaimBatch $batch): RedirectResponse
    {
        $this->claims->deleteDraft($batch);

        return redirect()->route('billing.claims')->with('success', 'Draft batch deleted; its bills are back in "To batch".');
    }

    public function removeBill(ClaimBatch $batch, Bill $bill): RedirectResponse
    {
        $this->claims->removeBill($batch, $bill);

        return back()->with('success', "{$bill->bill_number} removed from the batch.");
    }

    public function remit(Request $request, ClaimBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.paid' => ['nullable', 'numeric', 'min:0'],
            'lines.*.reason' => ['nullable', 'string', 'max:255'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $this->claims->remit($batch, $data['lines'], $data['paid_on'] ?? null, $data['payment_reference'] ?? null, $request->user());

        return back()->with('success', 'Remittance saved.');
    }

    public function export(ClaimBatch $batch): StreamedResponse
    {
        $rows = $this->claims->exportRows($batch);
        $headers = ['Batch', 'Bill', 'Enrollee ID', 'Enrollee name', 'Sex', 'Date of birth', 'Hospital no.', 'Encounter', 'Service date',
            'PA code', 'ICD-10', 'Diagnosis', 'Service', 'Qty', 'Unit price', 'Amount', 'Claimed'];

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map([ReportController::class, 'csvSafe'], array_values($row)));
            }
            fclose($out);
        }, "{$batch->batch_number}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function electronicFile(ClaimBatch $batch, ElectronicClaimService $electronic): \Symfony\Component\HttpFoundation\Response
    {
        return response(json_encode($electronic->payload($batch), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$batch->batch_number}.json\"",
        ]);
    }

    public function sendElectronic(ClaimBatch $batch, ElectronicClaimService $electronic): RedirectResponse
    {
        $reference = $electronic->submit($batch);

        return back()->with('success', 'Batch sent electronically'.($reference ? " — payer reference {$reference}" : '').'.');
    }

    public function printBatch(ClaimBatch $batch): View
    {
        $batch->load(['insuranceProvider', 'bills.patient', 'bills.items', 'bills.visit.consultation.diagnoses', 'bills.admission']);

        return view('claims.print-batch', ['batch' => $batch]);
    }

    public function form(Bill $bill): View
    {
        abort_if($bill->claim_status === 'none', 404);
        $bill->load(['patient', 'insuranceProvider', 'items', 'visit.clinic', 'visit.consultation.doctor', 'visit.consultation.diagnoses', 'admission.ward', 'claimBatch']);

        return view('claims.form', ['bill' => $bill]);
    }

    public function authorizationCode(Request $request, Bill $bill): RedirectResponse
    {
        $data = $request->validate(['authorization_code' => ['nullable', 'string', 'max:50']]);
        $this->claims->setAuthorizationCode($bill, $data['authorization_code'] ?? null, $request->user());

        return back()->with('success', "PA code saved on {$bill->bill_number}.");
    }

    public function requeue(Request $request, Bill $bill): RedirectResponse
    {
        $this->claims->requeue($bill, $request->user());

        return back()->with('success', "{$bill->bill_number} is back in \"To batch\" for resubmission.");
    }

    public function transfer(Request $request, Bill $bill): RedirectResponse
    {
        $amount = $this->claims->transferShortfall($bill, $request->user());

        return back()->with('success', money($amount)." moved to the patient's account on {$bill->bill_number}.");
    }
}
