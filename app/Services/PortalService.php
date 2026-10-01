<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Patient portal access: hospital staff issue a one-time activation code,
 * the patient uses it (with their hospital number) to set a password.
 */
class PortalService
{
    public const CODE_HOURS = 72;

    protected const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O, 1/I

    /**
     * Create or reset portal access. Returns the plain code (shown once).
     */
    public function issueAccess(Patient $patient, User $by, bool $sendSms = false): string
    {
        if ($patient->is_deceased) {
            throw ValidationException::withMessages(['portal' => 'Portal access cannot be given for a deceased patient.']);
        }

        $code = collect(range(1, 8))->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])->implode('');

        $account = PatientAccount::where('patient_id', $patient->id)->first() ?? new PatientAccount;
        $existing = $account->exists;
        $account->forceFill([
            'patient_id' => $patient->id,
            'password' => null,              // any old password stops working
            'activation_code' => Hash::make($code),
            'activation_expires_at' => now()->addHours(self::CODE_HOURS),
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'remember_token' => null,
            'created_by' => $account->created_by ?? $by->id,
        ])->save();

        $formatted = self::format($code);
        Audit::log($existing ? 'portal_access_reset' : 'portal_access_created', 'Patient portal access '.($existing ? 'reset' : 'created'), $patient);

        if ($sendSms) {
            $sms = app(SmsService::class);
            $body = $sms->render('{hospital} patient portal: your hospital number is {hn} and activation code {code} (valid '.self::CODE_HOURS.' hours). Visit {url}', $patient, [
                'hn' => $patient->hospital_number, 'code' => $formatted, 'url' => route('portal.activate'),
            ]);
            $sms->queue($patient, null, $body, 'portal_access', null, $by->id);
        }

        return $formatted;
    }

    public function disable(Patient $patient): void
    {
        PatientAccount::where('patient_id', $patient->id)->update(['is_active' => false, 'remember_token' => null]);
        Audit::log('portal_access_disabled', 'Patient portal access disabled', $patient);
    }

    public function activate(string $hospitalNumber, string $code, string $password): PatientAccount
    {
        $account = $this->find($hospitalNumber);
        $plain = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        $valid = $account && $account->is_active && ! $account->isLocked() && $account->activation_code
            && $account->activation_expires_at?->isFuture() && Hash::check($plain, $account->activation_code);

        if (! $valid) {
            $this->recordFailure($account);
            throw ValidationException::withMessages(['code' => 'The hospital number or activation code is wrong, or the code has expired. Ask the hospital for a new code.']);
        }

        $account->forceFill([
            'password' => $password,
            'activation_code' => null,
            'activation_expires_at' => null,
            'failed_login_attempts' => 0,
        ])->save();

        Audit::log('portal_activated', 'Patient activated portal access', $account->patient);

        return $account;
    }

    /**
     * Check credentials; returns the account or throws a generic error.
     */
    public function attempt(string $hospitalNumber, string $password): PatientAccount
    {
        $account = $this->find($hospitalNumber);

        if ($account?->isLocked()) {
            throw ValidationException::withMessages(['hospital_number' => 'Too many wrong attempts. Try again after '.$account->locked_until->format('h:i A').'.']);
        }

        if (! $account || ! $account->is_active || ! $account->isActivated() || ! Hash::check($password, $account->password)) {
            $this->recordFailure($account);
            throw ValidationException::withMessages(['hospital_number' => 'The hospital number or password is incorrect.']);
        }

        $account->forceFill(['failed_login_attempts' => 0, 'locked_until' => null, 'last_login_at' => now()])->save();

        return $account;
    }

    public static function format(string $code): string
    {
        return substr($code, 0, 4).'-'.substr($code, 4);
    }

    protected function find(string $hospitalNumber): ?PatientAccount
    {
        return PatientAccount::with('patient')
            ->whereHas('patient', fn ($q) => $q->where('hospital_number', strtoupper(trim($hospitalNumber)))->where('is_deceased', false))
            ->first();
    }

    protected function recordFailure(?PatientAccount $account): void
    {
        if (! $account) {
            return;
        }

        $attempts = $account->failed_login_attempts + 1;
        $max = (int) config('emr.security.max_failed_logins');

        if ($attempts >= $max) {
            $account->forceFill(['failed_login_attempts' => 0, 'locked_until' => now()->addMinutes((int) config('emr.security.lockout_minutes'))])->save();
            Audit::log('portal_locked', "Patient portal locked after {$max} failed attempts", $account->patient);

            return;
        }

        $account->forceFill(['failed_login_attempts' => $attempts])->save();
    }
}
