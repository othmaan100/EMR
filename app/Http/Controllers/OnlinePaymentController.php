<?php

namespace App\Http\Controllers;

use App\Integrations\Payments\OnlinePaymentService;
use App\Models\IntegrationMessage;
use App\Models\OnlinePayment;
use App\Models\Patient;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OnlinePaymentController extends Controller
{
    public function __construct(protected OnlinePaymentService $payments) {}

    /**
     * Cashier: create a payment link for the patient's unpaid items.
     */
    public function link(Request $request, Patient $patient, SmsService $sms): RedirectResponse
    {
        $data = $request->validate(['items' => ['nullable', 'array'], 'items.*' => ['integer'], 'send_sms' => ['boolean']]);
        $payment = $this->payments->start($patient, $data['items'] ?? [], 'link', $request->user());
        $url = route('payments.pay', $payment->reference);

        if ($request->boolean('send_sms') && $patient->phone) {
            $body = $sms->render('{hospital}: pay '.money($payment->amount).' for your bill online at {url}', $patient, ['url' => $url]);
            $sms->queue($patient, null, $body, 'payment_link', null, $request->user()->id);
        }

        return back()->with('payment_link', $url)->with('success', 'Payment link created for '.money($payment->amount).'.');
    }

    /**
     * Public: a payment link opens the gateway checkout.
     */
    public function pay(string $reference): RedirectResponse|View
    {
        $payment = OnlinePayment::where('reference', $reference)->firstOrFail();
        if ($payment->status === 'pending' && $payment->checkout_url && $payment->created_at->gt(now()->subDays(7))) {
            return redirect()->away($payment->checkout_url);
        }

        return view('payments.result', ['payment' => $payment]);
    }

    /**
     * Public: the gateway sends the payer back here. We verify server-to-server.
     */
    public function callback(Request $request): RedirectResponse|View
    {
        $reference = (string) ($request->query('reference') ?? $request->query('tx_ref') ?? $request->query('trxref'));
        $payment = $reference ? $this->payments->complete($reference) : null;
        abort_unless($payment, 404);

        if (Auth::guard('patient')->check()) {
            return redirect()->route('portal.bills')->with($payment->status === 'success' ? 'success' : 'warning',
                $payment->status === 'success' ? 'Thank you — your payment of '.money($payment->amount).' was received.' : 'The payment was not completed.');
        }

        return view('payments.result', ['payment' => $payment]);
    }

    /**
     * Gateway server → us. Signature checked before anything is done.
     */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        abort_unless(in_array($gateway, ['paystack', 'flutterwave'], true), 404);

        if (! $this->payments->enabled() || ! $this->payments->gateway($gateway)->webhookIsAuthentic($request)) {
            IntegrationMessage::record('payment', 'in', 'error', "Rejected {$gateway} webhook (bad signature)", null, null);

            return response()->json(['status' => 'rejected'], 401);
        }

        $reference = $this->payments->gateway($gateway)->webhookReference($request);
        if ($reference) {
            $this->payments->complete($reference);
        }

        return response()->json(['status' => 'ok']);
    }
}
