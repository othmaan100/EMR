<?php

namespace App\Integrations\Identity;

use App\Models\IntegrationMessage;
use App\Services\SmsService;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * National Identification Number (NIN) lookup through a licensed NIMC
 * verification partner. Returns only the fields used to fill the
 * registration form; nothing else from the response is stored.
 *
 * Endpoints follow the providers' published APIs; confirm them against
 * your contract before go-live (a custom base URL can be set).
 */
class NinVerifier
{
    public const PROVIDERS = ['none' => 'Not set up', 'dojah' => 'Dojah', 'prembly' => 'Prembly (IdentityPass)'];

    public function enabled(): bool
    {
        return in_array(setting('nin_provider'), ['dojah', 'prembly'], true) && filled(SmsService::secret('nin_api_key'));
    }

    /**
     * @return array{first_name: ?string, middle_name: ?string, last_name: ?string, date_of_birth: ?string, gender: ?string, phone: ?string, reference: string}
     */
    public function lookup(string $nin): array
    {
        if (! preg_match('/^\d{11}$/', $nin)) {
            throw ValidationException::withMessages(['national_id' => 'A NIN has exactly 11 digits.']);
        }
        if (! $this->enabled()) {
            throw ValidationException::withMessages(['national_id' => 'NIN verification is not set up (Hospital Settings → Integrations).']);
        }

        $masked = substr($nin, 0, 3).'*****'.substr($nin, -3);

        try {
            $person = match (setting('nin_provider')) {
                'dojah' => $this->dojah($nin),
                'prembly' => $this->prembly($nin),
            };
        } catch (ValidationException $e) {
            IntegrationMessage::record('nin', 'out', 'error', "NIN {$masked}: ".implode(' ', $e->validator->errors()->all()));

            throw $e;
        } catch (Throwable $e) {
            IntegrationMessage::record('nin', 'out', 'error', "NIN {$masked}: service error — {$e->getMessage()}");

            throw ValidationException::withMessages(['national_id' => 'The NIN service could not be reached. Try again later.']);
        }

        $person['reference'] = setting('nin_provider').':'.now()->format('YmdHis');
        IntegrationMessage::record('nin', 'out', 'ok', "NIN {$masked} verified");
        Audit::log('nin_lookup', "NIN {$masked} looked up");

        return $person;
    }

    protected function dojah(string $nin): array
    {
        $response = Http::timeout(20)->acceptJson()->withHeaders([
            'AppId' => (string) setting('nin_app_id'),
            'Authorization' => (string) SmsService::secret('nin_api_key'),
        ])->get(rtrim(setting('nin_base_url') ?: 'https://api.dojah.io', '/').'/api/v1/kyc/nin', ['nin' => $nin]);

        $this->assertFound($response->status(), $response->json('entity'));
        $e = $response->json('entity');

        return $this->person($e['first_name'] ?? null, $e['middle_name'] ?? null, $e['last_name'] ?? null, $e['date_of_birth'] ?? null, $e['gender'] ?? null, $e['phone_number'] ?? null);
    }

    protected function prembly(string $nin): array
    {
        $response = Http::timeout(20)->acceptJson()->withHeaders([
            'x-api-key' => (string) SmsService::secret('nin_api_key'),
            'app-id' => (string) setting('nin_app_id'),
        ])->post(rtrim(setting('nin_base_url') ?: 'https://api.prembly.com', '/').'/identitypass/verification/nin', ['number' => $nin]);

        $d = $response->json('nin_data');
        $this->assertFound($response->status(), $response->json('status') ? $d : null);

        return $this->person($d['firstname'] ?? null, $d['middlename'] ?? null, $d['surname'] ?? null, $d['birthdate'] ?? null, $d['gender'] ?? null, $d['telephoneno'] ?? null);
    }

    protected function assertFound(int $status, mixed $data): void
    {
        if ($status === 404 || ($status < 400 && empty($data))) {
            throw ValidationException::withMessages(['national_id' => 'No record was found for this NIN.']);
        }
        if ($status >= 400) {
            throw new \RuntimeException("HTTP {$status}");
        }
    }

    protected function person(?string $first, ?string $middle, ?string $last, ?string $dob, ?string $gender, ?string $phone): array
    {
        $name = fn (?string $v) => $v ? ucwords(strtolower(trim($v))) : null;
        $date = null;
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd-M-Y'] as $format) {
            try {
                $date = $dob ? Carbon::createFromFormat('!'.$format, trim($dob))->toDateString() : null;
                break;
            } catch (Throwable) {
            }
        }
        $g = strtolower(trim((string) $gender));

        return [
            'first_name' => $name($first),
            'middle_name' => $name($middle),
            'last_name' => $name($last),
            'date_of_birth' => $date,
            'gender' => in_array($g, ['m', 'male'], true) ? 'male' : (in_array($g, ['f', 'female'], true) ? 'female' : null),
            'phone' => $phone ? preg_replace('/^\+?234/', '0', preg_replace('/\D/', '', $phone)) : null,
        ];
    }
}
