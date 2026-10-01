<?php

namespace App\Integrations\Payments;

use App\Models\BillItem;
use App\Models\IntegrationMessage;
use App\Models\OnlinePayment;
use App\Models\Patient;
use App\Models\User;
use App\Services\BillingService;
use App\Services\SmsService;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Online payment of a patient's bill items through Paystack or Flutterwave.
 * A payment is only ever recorded after the gateway itself confirms it.
 */
class OnlinePaymentService
{
    public function __construct(protected BillingService $billing) {}

    public function enabled(): bool
    {
        return in_array(setting('payment_gateway'), ['paystack', 'flutterwave'], true) && filled(SmsService::secret('payment_secret_key'));
    }

    public function gateway(?string $name = null): PaymentGateway
    {
        $name ??= setting('payment_gateway');
        $secret = (string) SmsService::secret('payment_secret_key');

        return match ($name) {
            'paystack' => new PaystackGateway($secret),
            'flutterwave' => new FlutterwaveGateway($secret, SmsService::secret('payment_webhook_hash')),
            default => throw new RuntimeException('No online payment gateway is set up.'),
        };
    }

    /**
     * Create a checkout for the patient's unpaid items (all, or the chosen ones).
     */
    public function start(Patient $patient, array $itemIds, string $channel, ?User $by = null): OnlinePayment
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages(['payment' => 'Online payment is not available.']);
        }

        $items = BillItem::unpaid()->whereHas('bill', fn ($q) => $q->where('patient_id', $patient->id))
            ->when($itemIds, fn ($q) => $q->whereIn('id', $itemIds))->get();
        $amount = round($items->sum(fn (BillItem $i) => $i->outstanding()), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['payment' => 'There is nothing to pay.']);
        }

        $payment = OnlinePayment::create([
            'reference' => 'EMR-'.Str::upper(Str::random(24)),
            'gateway' => setting('payment_gateway'),
            'patient_id' => $patient->id,
            'amount' => $amount,
            'currency' => setting('currency_code', 'NGN'),
            'bill_item_ids' => $items->pluck('id')->all(),
            'status' => 'pending',
            'channel' => $channel,
            'created_by' => $by?->id,
        ]);

        try {
            $url = $this->gateway()->initialize($payment, $this->payerEmail($patient), $patient->full_name, $patient->phone, route('payments.callback'));
        } catch (Throwable $e) {
            $payment->forceFill(['status' => 'failed'])->save();
            IntegrationMessage::record('payment', 'out', 'error', "Checkout for {$patient->hospital_number} failed: {$e->getMessage()}", $payment->reference, null, $payment);

            throw ValidationException::withMessages(['payment' => 'The payment service could not be reached. Please try again or pay at the cashier.']);
        }

        $payment->forceFill(['checkout_url' => $url])->save();
        IntegrationMessage::record('payment', 'out', 'ok', "Checkout of ".money($amount)." for {$patient->hospital_number} ({$channel})", $payment->reference, null, $payment);

        return $payment;
    }

    /**
     * Confirm with the gateway and post the payment (safe to call repeatedly).
     */
    public function complete(string $reference): ?OnlinePayment
    {
        $payment = OnlinePayment::where('reference', $reference)->first();
        if (! $payment) {
            return null;
        }

        return DB::transaction(function () use ($payment) {
            $payment = OnlinePayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'success') {
                return $payment; // already posted (callback and webhook both arrive)
            }

            try {
                $result = $this->gateway($payment->gateway)->verify($payment);
            } catch (Throwable $e) {
                IntegrationMessage::record('payment', 'out', 'error', "Verification of {$payment->reference} failed: {$e->getMessage()}", $payment->reference, null, $payment);

                return $payment;
            }

            if (! $result['paid']) {
                IntegrationMessage::record('payment', 'in', 'error', "Not paid: {$result['message']}", $payment->reference, null, $payment);

                return $payment;
            }
            if (strtoupper($result['currency']) !== strtoupper($payment->currency) || $result['amount'] <= 0) {
                IntegrationMessage::record('payment', 'in', 'error', "Currency/amount mismatch ({$result['currency']} {$result['amount']}) — not posted; check with the gateway.", $payment->reference, null, $payment);

                return $payment;
            }

            $received = round($result['amount'], 2);
            $patient = $payment->patient;
            $stillDue = round(BillItem::unpaid()->whereIn('id', $payment->bill_item_ids)->get()->sum(fn (BillItem $i) => $i->outstanding()), 2);

            $posted = null;
            if ($stillDue > 0) {
                $posted = $this->billing->pay($patient, $payment->bill_item_ids, min($received, $stillDue), 'online', $payment->reference, null);
            }
            // Anything more (e.g. the cashier was paid meanwhile) is kept as a deposit, never lost.
            if ($received - ($posted?->amount ?? 0) > 0.004) {
                $deposit = $this->billing->deposit($patient, round($received - ($posted?->amount ?? 0), 2), 'online', $payment->reference, null);
                $posted ??= $deposit;
            }

            $payment->forceFill(['status' => 'success', 'paid_at' => now(), 'payment_id' => $posted?->id, 'gateway_reference' => $result['gateway_reference']])->save();
            IntegrationMessage::record('payment', 'in', 'ok', 'Paid '.money($received)." by {$patient->hospital_number}", $payment->reference, null, $payment);
            Audit::log('online_payment_received', 'Online payment of '.money($received)." ({$payment->gateway}, {$payment->reference})", $patient);

            return $payment;
        });
    }

    /**
     * Paystack needs an email; fall back to the hospital's address.
     */
    protected function payerEmail(Patient $patient): string
    {
        return $patient->email ?: (setting('email') ?: 'payments@'.parse_url((string) config('app.url'), PHP_URL_HOST));
    }
}
