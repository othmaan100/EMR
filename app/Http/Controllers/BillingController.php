<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Preauthorization;
use App\Models\Service;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(protected BillingService $billing) {}

    /**
     * Cashier home: find a patient, see who owes, and today's cash book.
     */
    public function index(Request $request): View
    {
        $date = rescue(fn () => Carbon::parse($request->query('date', today()->toDateString())), today(), false);

        $owing = BillItem::unpaid()
            ->join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->selectRaw('bills.patient_id, SUM(patient_amount - discount_amount - paid_amount) as due, MAX(bill_items.created_at) as last_charge')
            ->groupBy('bills.patient_id')
            ->orderByDesc('last_charge')
            ->limit(30)->get();
        $patients = Patient::whereIn('id', $owing->pluck('patient_id'))->get()->keyBy('id');

        $payments = Payment::with(['patient', 'receiver'])->whereDate('created_at', $date)->latest('id')->get();

        return view('billing.index', [
            'owing' => $owing->map(fn ($row) => ['patient' => $patients[$row->patient_id] ?? null, 'due' => (float) $row->due, 'last' => $row->last_charge])
                ->filter(fn ($r) => $r['patient']),
            'payments' => $payments,
            'date' => $date,
            'byMethod' => $payments->whereNull('voided_at')->groupBy('method')->map->sum('amount'),
            'byCashier' => $payments->whereNull('voided_at')->groupBy(fn ($p) => $p->receiver?->name ?? '—')->map->sum('amount'),
        ]);
    }

    public function account(Patient $patient): View
    {
        $bills = $patient->bills()->with(['items.creator', 'visit.clinic', 'insuranceProvider'])->latest('id')->get();

        return view('billing.account', [
            'patient' => $patient,
            'bills' => $bills,
            'unpaid' => $bills->flatMap->items->filter(fn (BillItem $i) => ! $i->voided_at && $i->outstanding() > 0)->sortBy('id'),
            'credit' => (float) $bills->flatMap->items->filter(fn (BillItem $i) => $i->voided_at && $i->paid_amount > 0)->sum('paid_amount'),
            'payments' => Payment::with(['receiver', 'voider'])->where('patient_id', $patient->id)->latest('id')->limit(30)->get(),
            'services' => Service::active()->with('prices')->orderBy('category')->orderBy('name')->get()->filter(fn ($s) => $s->priceFor() !== null),
            'openVisit' => $patient->visits()->open()->first(),
            'deposit' => $this->billing->depositBalance($patient),
            'preauths' => Preauthorization::with('bill')->where('patient_id', $patient->id)->latest('id')->limit(5)->get(),
        ]);
    }

    public function pay(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in([...array_keys(Payment::cashierMethods()), 'deposit'])],
            'reference' => ['nullable', Rule::requiredIf(! in_array($request->input('method'), ['cash', 'deposit'], true)), 'string', 'max:100'],
        ], [
            'items.required' => 'Tick the items being paid for.',
            'reference.required' => 'Enter the transaction / reference number for non-cash payments.',
        ]);

        if ($data['method'] === 'deposit') {
            $used = $this->billing->payFromDeposit($patient, $data['items'], (float) $data['amount'], $request->user());

            return redirect()->route('billing.account', $patient)->with('success', money($used).' paid from the patient\'s deposit.');
        }

        $payment = $this->billing->pay($patient, $data['items'], (float) $data['amount'], $data['method'], $data['reference'] ?? null, $request->user());

        return redirect()->route('billing.account', $patient)
            ->with('success', 'Payment of '.money($payment->amount)." received. Receipt {$payment->receipt_number}.")
            ->with('receipt', $payment->id);
    }

    public function deposit(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validateWithBag('deposit', [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(array_keys(Payment::cashierMethods()))],
            'reference' => ['nullable', 'required_unless:method,cash', 'string', 'max:100'],
        ], ['reference.required_unless' => 'Enter the transaction / reference number for non-cash payments.']);

        $payment = $this->billing->deposit($patient, (float) $data['amount'], $data['method'], $data['reference'] ?? null, $request->user());

        return redirect()->route('billing.account', $patient)
            ->with('success', 'Deposit of '.money($payment->amount)." received. Receipt {$payment->receipt_number}.")
            ->with('receipt', $payment->id);
    }

    public function addCharge(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['required', Rule::exists('services', 'id')->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:1000'],
        ]);

        $service = Service::findOrFail($data['service_id']);
        $item = DB::transaction(fn () => $this->billing->charge(
            $patient, $patient->visits()->open()->first(), $service, null, (float) $data['quantity'], $request->user()
        ));

        return back()->with($item ? 'success' : 'error', $item ? "Charged {$service->name}." : "{$service->name} has no price set.");
    }

    public function discount(Request $request, BillItem $item): RedirectResponse
    {
        $data = $request->validateWithBag('discount', [
            'discount_amount' => ['required', 'numeric', 'min:0'],
            'discount_reason' => ['required', 'string', 'max:255'],
        ]);

        $this->billing->discount($item, (float) $data['discount_amount'], $data['discount_reason'], $request->user());

        return back()->with('success', 'Discount applied.');
    }

    public function voidItem(Request $request, BillItem $item): RedirectResponse
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:255']]);
        $this->billing->voidItem($item, $request->user(), $data['void_reason']);

        return back()->with('success', 'Charge voided.');
    }

    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:255']]);
        $this->billing->voidPayment($payment, $data['void_reason'], $request->user());

        return back()->with('success', "Receipt {$payment->receipt_number} reversed.");
    }

    public function receipt(Payment $payment): View
    {
        return view('billing.receipt', ['payment' => $payment->load(['patient', 'receiver', 'allocations.item.bill'])]);
    }

    public function invoice(Bill $bill): View
    {
        return view('billing.invoice', ['bill' => $bill->load(['patient', 'items', 'visit.clinic', 'insuranceProvider'])]);
    }
}
