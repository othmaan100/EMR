<?php

namespace App\Integrations\Payments;

use App\Models\OnlinePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paystack (https://paystack.com/docs/api/). Amounts are sent in kobo.
 */
class PaystackGateway implements PaymentGateway
{
    public const BASE = 'https://api.paystack.co';

    public function __construct(protected string $secretKey) {}

    public function initialize(OnlinePayment $payment, string $email, string $name, ?string $phone, string $callbackUrl): string
    {
        $response = Http::timeout(20)->withToken($this->secretKey)->acceptJson()->post(self::BASE.'/transaction/initialize', [
            'email' => $email,
            'amount' => (int) round($payment->amount * 100),
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'callback_url' => $callbackUrl,
            'metadata' => ['patient' => $name, 'hospital_number' => $payment->patient->hospital_number],
        ]);

        if ($response->failed() || ! $response->json('status')) {
            throw new RuntimeException('Paystack: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return (string) $response->json('data.authorization_url');
    }

    public function verify(OnlinePayment $payment): array
    {
        $response = Http::timeout(20)->withToken($this->secretKey)->acceptJson()->get(self::BASE.'/transaction/verify/'.rawurlencode($payment->reference));
        if ($response->failed()) {
            throw new RuntimeException('Paystack: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return [
            'paid' => $response->json('data.status') === 'success',
            'amount' => ((int) $response->json('data.amount')) / 100,
            'currency' => (string) $response->json('data.currency'),
            'gateway_reference' => (string) ($response->json('data.id') ?? ''),
            'message' => (string) ($response->json('data.gateway_response') ?? $response->json('data.status')),
        ];
    }

    public function webhookIsAuthentic(Request $request): bool
    {
        $signature = (string) $request->header('x-paystack-signature');

        return $signature !== '' && hash_equals(hash_hmac('sha512', $request->getContent(), $this->secretKey), $signature);
    }

    public function webhookReference(Request $request): ?string
    {
        return $request->input('data.reference');
    }
}
