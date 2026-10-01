<?php

namespace App\Integrations\Payments;

use App\Models\OnlinePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Flutterwave Standard checkout, API v3 (https://developer.flutterwave.com/).
 */
class FlutterwaveGateway implements PaymentGateway
{
    public const BASE = 'https://api.flutterwave.com/v3';

    public function __construct(protected string $secretKey, protected ?string $webhookHash) {}

    public function initialize(OnlinePayment $payment, string $email, string $name, ?string $phone, string $callbackUrl): string
    {
        $response = Http::timeout(20)->withToken($this->secretKey)->acceptJson()->post(self::BASE.'/payments', [
            'tx_ref' => $payment->reference,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'redirect_url' => $callbackUrl,
            'customer' => array_filter(['email' => $email, 'name' => $name, 'phonenumber' => $phone]),
            'customizations' => ['title' => setting('hospital_name')],
            'meta' => ['hospital_number' => $payment->patient->hospital_number],
        ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            throw new RuntimeException('Flutterwave: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return (string) $response->json('data.link');
    }

    public function verify(OnlinePayment $payment): array
    {
        $response = Http::timeout(20)->withToken($this->secretKey)->acceptJson()
            ->get(self::BASE.'/transactions/verify_by_reference', ['tx_ref' => $payment->reference]);
        if ($response->failed()) {
            throw new RuntimeException('Flutterwave: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return [
            'paid' => $response->json('data.status') === 'successful',
            'amount' => (float) $response->json('data.amount'),
            'currency' => (string) $response->json('data.currency'),
            'gateway_reference' => (string) ($response->json('data.id') ?? ''),
            'message' => (string) ($response->json('data.processor_response') ?? $response->json('data.status')),
        ];
    }

    public function webhookIsAuthentic(Request $request): bool
    {
        $hash = (string) $request->header('verif-hash');

        return filled($this->webhookHash) && $hash !== '' && hash_equals($this->webhookHash, $hash);
    }

    public function webhookReference(Request $request): ?string
    {
        return $request->input('data.tx_ref') ?? $request->input('txRef');
    }
}
