<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\SmsMessage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Outgoing SMS. Messages are queued in sms_messages and delivered by
 * `emr:send-sms` (every minute), so a slow provider never blocks a request.
 * Provider "none" records messages without sending ("logged only").
 */
class SmsService
{
    public const PROVIDERS = [
        'none' => 'Log only (no SMS sent)',
        'termii' => 'Termii',
        'africastalking' => "Africa's Talking",
        'twilio' => 'Twilio',
    ];

    public const MAX_ATTEMPTS = 3;

    /**
     * Queue a message. Returns null if the patient has no usable phone number
     * or the dedupe key was already used (message already sent before).
     */
    public function queue(?Patient $patient, ?string $phone, string $body, string $type, ?string $dedupeKey = null, ?int $userId = null): ?SmsMessage
    {
        $phone = $this->normalise($phone ?? $patient?->phone ?? $patient?->nok_phone);
        if (! $phone) {
            return null;
        }

        try {
            return SmsMessage::create([
                'patient_id' => $patient?->id,
                'phone' => $phone,
                'body' => Str::limit($body, 640, ''),
                'type' => $type,
                'status' => 'queued',
                'dedupe_key' => $dedupeKey,
                'created_by' => $userId,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null; // already queued/sent for this event
        }
    }

    /**
     * Fill a template: {name}, {hospital}, {phone} plus any extras.
     */
    public function render(string $template, ?Patient $patient, array $extra = []): string
    {
        $values = array_merge([
            'name' => $patient ? trim($patient->first_name) : '',
            'hospital' => setting('hospital_short_name') ?: setting('hospital_name'),
            'phone' => setting('phone'),
        ], $extra);

        return strtr($template, collect($values)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }

    /**
     * Deliver one message now (used by the queue worker and "send test").
     */
    public function deliver(SmsMessage $message): SmsMessage
    {
        $provider = setting('sms_provider', 'none') ?: 'none';
        $message->provider = $provider;
        $message->attempts++;

        if ($provider === 'none') {
            $message->forceFill(['status' => 'logged', 'sent_at' => now(), 'error' => null])->save();
            $this->redact($message);

            return $message;
        }

        try {
            $id = match ($provider) {
                'termii' => $this->viaTermii($message),
                'africastalking' => $this->viaAfricasTalking($message),
                'twilio' => $this->viaTwilio($message),
                default => throw new RuntimeException("Unknown SMS provider \"{$provider}\"."),
            };
            $message->forceFill(['status' => 'sent', 'provider_message_id' => $id, 'sent_at' => now(), 'error' => null])->save();
            $this->redact($message);
        } catch (Throwable $e) {
            $message->forceFill([
                'status' => $message->attempts >= self::MAX_ATTEMPTS ? 'failed' : 'queued',
                'error' => Str::limit($e->getMessage(), 490),
            ])->save();
        }

        return $message;
    }

    /**
     * Send everything queued (called every minute by the scheduler).
     */
    public function flush(int $limit = 100): int
    {
        $sent = 0;
        SmsMessage::where('status', 'queued')->oldest('id')->limit($limit)->get()
            ->each(function (SmsMessage $m) use (&$sent) {
                if (in_array($this->deliver($m)->status, ['sent', 'logged'], true)) {
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * To international format without "+": 08031234567 → 2348031234567.
     */
    public function normalise(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 7) {
            return null;
        }

        $country = preg_replace('/\D+/', '', (string) setting('sms_country_code', '234'));
        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && $country) {
            return $country.substr($digits, 1);
        }

        return $digits;
    }

    public static function secret(string $key): ?string
    {
        $value = setting($key);
        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return null;
        }
    }

    // ---------------------------------------------------------------- providers

    protected function viaTermii(SmsMessage $m): ?string
    {
        $response = Http::timeout(15)->acceptJson()->post(setting('sms_base_url') ?: 'https://api.ng.termii.com/api/sms/send', [
            'api_key' => $this->required(self::secret('sms_api_key'), 'API key'),
            'to' => $m->phone,
            'from' => setting('sms_sender_id') ?: 'N-Alert',
            'sms' => $m->body,
            'type' => 'plain',
            'channel' => 'generic',
        ]);

        if ($response->failed() || $response->json('code') && $response->json('code') !== 'ok') {
            throw new RuntimeException('Termii: '.($response->json('message') ?? $response->status()));
        }

        return (string) $response->json('message_id');
    }

    protected function viaAfricasTalking(SmsMessage $m): ?string
    {
        $username = $this->required(setting('sms_username'), 'username');
        $url = $username === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';

        $response = Http::timeout(15)->acceptJson()->asForm()
            ->withHeaders(['apiKey' => $this->required(self::secret('sms_api_key'), 'API key')])
            ->post($url, array_filter([
                'username' => $username,
                'to' => '+'.$m->phone,
                'message' => $m->body,
                'from' => setting('sms_sender_id'),
            ]));

        $recipient = $response->json('SMSMessageData.Recipients.0');
        if ($response->failed() || ! $recipient || ($recipient['status'] ?? '') !== 'Success') {
            throw new RuntimeException("Africa's Talking: ".($recipient['status'] ?? $response->json('SMSMessageData.Message') ?? $response->status()));
        }

        return $recipient['messageId'] ?? null;
    }

    protected function viaTwilio(SmsMessage $m): ?string
    {
        $sid = $this->required(setting('sms_username'), 'Account SID');

        $response = Http::timeout(15)->acceptJson()->asForm()
            ->withBasicAuth($sid, $this->required(self::secret('sms_api_key'), 'Auth token'))
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => '+'.$m->phone,
                'From' => $this->required(setting('sms_sender_id'), 'sender number'),
                'Body' => $m->body,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Twilio: '.($response->json('message') ?? $response->status()));
        }

        return $response->json('sid');
    }

    /**
     * Once handed to the provider, one-time codes are blanked in the log.
     */
    protected function redact(SmsMessage $message): void
    {
        if ($message->type === 'portal_access') {
            $message->forceFill(['body' => preg_replace('/\b[A-Z0-9]{4}-[A-Z0-9]{4}\b/', '••••-••••', $message->body)])->save();
        }
    }

    protected function required(?string $value, string $what): string
    {
        if (blank($value)) {
            throw new RuntimeException("SMS {$what} is not configured.");
        }

        return $value;
    }
}
