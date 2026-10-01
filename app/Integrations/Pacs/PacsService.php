<?php

namespace App\Integrations\Pacs;

use App\Models\ImagingOrder;
use App\Models\IntegrationMessage;
use App\Models\User;
use App\Services\RadiologyService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * PACS link over DICOMweb (QIDO-RS), e.g. Orthanc, dcm4chee, or any
 * vendor PACS with DICOMweb. The imaging order number is used as the
 * DICOM Accession Number, typed or selected at the modality.
 */
class PacsService
{
    public function enabled(): bool
    {
        return (bool) setting('pacs_enabled') && filled(setting('pacs_dicomweb_url'));
    }

    /**
     * Study Instance UID for an order's accession number, or null.
     */
    public function findStudy(string $accessionNumber): ?string
    {
        $response = $this->http()->get(rtrim(setting('pacs_dicomweb_url'), '/').'/studies', [
            'AccessionNumber' => $accessionNumber,
            'includefield' => '0020000D',
        ]);

        if ($response->status() === 204) {
            return null; // no match
        }
        if ($response->failed()) {
            throw new RuntimeException('PACS answered HTTP '.$response->status().'.');
        }

        // DICOM JSON: tag 0020000D = Study Instance UID.
        return $response->json('0.0020000D.Value.0');
    }

    /**
     * Look for the study of one order and link it. Returns true when linked.
     */
    public function link(ImagingOrder $order, ?User $by = null): bool
    {
        try {
            $uid = $this->findStudy($order->order_number);
        } catch (Throwable $e) {
            IntegrationMessage::record('pacs', 'out', 'error', "Study search for {$order->order_number} failed: {$e->getMessage()}", $order->order_number, null, $order);

            throw $e;
        }

        if (! $uid) {
            return false;
        }

        $order->forceFill(['study_instance_uid' => $uid, 'pacs_linked_at' => now()])->save();
        IntegrationMessage::record('pacs', 'in', 'ok', "Images found for {$order->order_number}", $order->order_number, null, $order);

        // Images exist, so the examination was done. Respect "pay before service".
        if (in_array($order->status, ['requested', 'scheduled'], true) && $by) {
            try {
                app(RadiologyService::class)->perform($order, $by);
            } catch (ValidationException) {
                // e.g. unpaid: leave it for the radiographer to mark performed after payment
            }
        }

        return true;
    }

    /**
     * Link recent orders that have no study yet (scheduled every 5 minutes).
     */
    public function sync(User $by): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $linked = 0;
        ImagingOrder::whereNull('study_instance_uid')->whereIn('status', ['requested', 'scheduled', 'performed'])
            ->where('created_at', '>=', now()->subDays(14))->orderBy('id')->limit(200)->get()
            ->each(function (ImagingOrder $order) use ($by, &$linked) {
                try {
                    $linked += $this->link($order, $by) ? 1 : 0;
                } catch (Throwable) {
                    // logged in link(); keep going
                }
            });

        return $linked;
    }

    public function viewerUrl(ImagingOrder $order): ?string
    {
        $template = setting('pacs_viewer_url');
        if (! $order->study_instance_uid || ! $template) {
            return null;
        }

        return str_replace(['{study}', '{accession}'], [urlencode($order->study_instance_uid), urlencode($order->order_number)], $template);
    }

    public function test(): string
    {
        $response = $this->http()->get(rtrim(setting('pacs_dicomweb_url'), '/').'/studies', ['limit' => 1]);
        if ($response->failed()) {
            throw new RuntimeException('PACS answered HTTP '.$response->status().'.');
        }

        return 'Connected to PACS (DICOMweb answered).';
    }

    protected function http()
    {
        $request = Http::timeout(15)->accept('application/dicom+json');
        if (filled(setting('pacs_username'))) {
            $request = $request->withBasicAuth(setting('pacs_username'), (string) SmsService::secret('pacs_password'));
        }

        return $request;
    }
}
