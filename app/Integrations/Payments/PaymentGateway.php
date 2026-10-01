<?php

namespace App\Integrations\Payments;

use App\Models\OnlinePayment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /**
     * Start a checkout; returns the URL to send the payer to.
     */
    public function initialize(OnlinePayment $payment, string $email, string $name, ?string $phone, string $callbackUrl): string;

    /**
     * Ask the gateway (never trust the browser) whether the payment succeeded.
     *
     * @return array{paid: bool, amount: float, currency: string, gateway_reference: ?string, message: string}
     */
    public function verify(OnlinePayment $payment): array;

    /**
     * Whether a webhook really came from the gateway.
     */
    public function webhookIsAuthentic(Request $request): bool;

    /**
     * Our transaction reference carried in a webhook, if any.
     */
    public function webhookReference(Request $request): ?string;
}
