<?php

namespace App\Support;

use App\Services\SmsService;
use DateTimeZone;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validation and persistence for the hospital's profile settings.
 * Shared by the setup wizard and the admin Hospital Settings page.
 */
class HospitalProfile
{
    public const SECTIONS = ['profile', 'contact', 'preferences', 'security', 'sms', 'integrations'];

    /** Settings kept encrypted (see SmsService::secret()). */
    public const SECRETS = ['sms_api_key', 'pacs_password', 'payment_secret_key', 'payment_webhook_hash', 'claims_api_key', 'nin_api_key'];

    public function __construct(protected Settings $settings) {}

    public static function rules(string $section): array
    {
        return match ($section) {
            'profile' => [
                'hospital_name' => ['required', 'string', 'max:150'],
                'hospital_short_name' => ['nullable', 'string', 'max:30'],
                'hospital_type' => ['required', Rule::in(config('emr.hospital_types'))],
                'registration_number' => ['nullable', 'string', 'max:100'],
                'motto' => ['nullable', 'string', 'max:200'],
                // SVG is excluded on purpose: it can carry scripts.
                'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
                'remove_logo' => ['nullable', 'boolean'],
            ],
            'contact' => [
                'address' => ['required', 'string', 'max:255'],
                'city' => ['required', 'string', 'max:100'],
                'state' => ['nullable', 'string', 'max:100'],
                'country' => ['required', 'string', 'max:100'],
                'postal_code' => ['nullable', 'string', 'max:20'],
                'phone' => ['required', 'string', 'max:30'],
                'alt_phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:150'],
                'website' => ['nullable', 'url', 'max:150'],
            ],
            'preferences' => [
                'currency_code' => ['required', Rule::in(array_keys(config('emr.currencies')))],
                'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
                'date_format' => ['required', Rule::in(array_keys(config('emr.date_formats')))],
                'patient_number_prefix' => ['required', 'alpha_dash', 'max:10'],
                'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'lab_self_verify' => ['boolean'],
                'bill_before_service' => ['boolean'],
                'pharmacy_pay_first' => ['boolean'],
                'portal_enabled' => ['boolean'],
            ],
            'integrations' => [
                'pacs_enabled' => ['boolean'],
                'pacs_dicomweb_url' => ['nullable', 'url', 'max:255'],
                'pacs_username' => ['nullable', 'string', 'max:100'],
                'pacs_password' => ['nullable', 'string', 'max:255'],
                'pacs_viewer_url' => ['nullable', 'string', 'max:255', 'starts_with:http://,https://'],
                'payment_gateway' => ['required', 'in:none,paystack,flutterwave'],
                'payment_secret_key' => ['nullable', 'string', 'max:255'],
                'payment_webhook_hash' => ['nullable', 'string', 'max:255'],
                'portal_online_payments' => ['boolean'],
                'claims_provider_code' => ['nullable', 'string', 'max:50'],
                'claims_api_url' => ['nullable', 'url:https', 'max:255'],
                'claims_api_key' => ['nullable', 'string', 'max:255'],
                'nin_provider' => ['required', 'in:none,dojah,prembly'],
                'nin_app_id' => ['nullable', 'string', 'max:100'],
                'nin_api_key' => ['nullable', 'string', 'max:255'],
                'nin_base_url' => ['nullable', 'url:https', 'max:255'],
            ],
            'sms' => [
                'sms_provider' => ['required', Rule::in(array_keys(SmsService::PROVIDERS))],
                'sms_sender_id' => ['nullable', 'string', 'max:20'],
                'sms_username' => ['nullable', 'string', 'max:100'],
                'sms_api_key' => ['nullable', 'string', 'max:255'],   // blank = keep the saved key
                'sms_base_url' => ['nullable', 'url:https', 'max:255'],
                'sms_country_code' => ['required', 'digits_between:1,4'],
                'sms_appointment_reminders' => ['boolean'],
                'sms_results_ready' => ['boolean'],
                'sms_immunization_reminders' => ['boolean'],
                'sms_tpl_appointment' => ['nullable', 'string', 'max:480'],
                'sms_tpl_results' => ['nullable', 'string', 'max:480'],
                'sms_tpl_immunization' => ['nullable', 'string', 'max:480'],
            ],
            'security' => [
                'session_idle_minutes' => ['required', 'integer', 'between:5,480'],
                'backup_retention_days' => ['required', 'integer', 'between:1,365'],
            ],
        };
    }

    /**
     * Validate a section from the request and save it. Returns saved values.
     */
    public function save(Request $request, string $section): array
    {
        $data = $request->validate(self::rules($section));

        if ($section === 'profile') {
            $data = $this->handleLogo($request, $data);
        }

        if ($section === 'preferences') {
            $data['patient_number_prefix'] = Str::upper($data['patient_number_prefix']);
            $data['currency_symbol'] = config("emr.currencies.{$data['currency_code']}.symbol");
        }

        // Keys and passwords are stored encrypted, never logged or shown again;
        // a blank field keeps the saved value.
        $secretsChanged = [];
        foreach (self::SECRETS as $secret) {
            if (! array_key_exists($secret, $data)) {
                continue;
            }
            if (filled($data[$secret])) {
                $this->settings->set([$secret => Crypt::encryptString($data[$secret])]);
                $secretsChanged[$secret] = '(changed)';
            }
            unset($data[$secret]);
        }

        $old = Arr::only($this->settings->all(), array_keys($data));
        $this->settings->set($data);

        $changed = array_diff_assoc(array_map('strval', $data), array_map('strval', $old));
        $changed += $secretsChanged;
        if ($changed) {
            Audit::log('settings_updated', 'Hospital settings updated ('.$section.')', null, Arr::only($old, array_keys($changed)), $changed);
        }

        return $data;
    }

    protected function handleLogo(Request $request, array $data): array
    {
        $current = $this->settings->get('logo');
        $upload = $request->file('logo');
        $remove = (bool) ($data['remove_logo'] ?? false);
        unset($data['logo'], $data['remove_logo']);

        if ($upload instanceof UploadedFile) {
            $data['logo'] = $this->storeLogo($upload);
        } elseif ($remove) {
            $data['logo'] = null;
        } else {
            return $data;
        }

        if ($current) {
            Storage::disk(config('emr.upload_disk'))->delete(Str::after($current, 'uploads/'));
        }

        return $data;
    }

    protected function storeLogo(UploadedFile $file): string
    {
        $name = 'logo-'.Str::random(12).'.'.$file->guessExtension();
        $path = $file->storeAs('branding', $name, config('emr.upload_disk'));

        // Stored relative to /public so asset() resolves it on any host.
        return 'uploads/'.$path;
    }
}
