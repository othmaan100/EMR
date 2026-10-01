<?php

namespace App\Services;

use App\Models\ImagingAttachment;
use App\Models\ImagingOrder;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Imaging workflow: requested → (scheduled) → performed → completed (signed report).
 */
class RadiologyService
{
    public function schedule(ImagingOrder $order, Carbon $when): void
    {
        $this->assertStatus($order, ['requested', 'scheduled'], 'schedule');
        $order->forceFill(['status' => 'scheduled', 'scheduled_for' => $when])->save();
    }

    public function perform(ImagingOrder $order, User $by): void
    {
        $this->assertStatus($order, ['requested', 'scheduled'], 'mark as performed');
        app(BillingService::class)->assertPaid(collect([$order]), 'the examination');

        $order->forceFill([
            'status' => 'performed',
            'performed_at' => now(),
            'performed_by' => $by->id,
            // Start the report from the procedure's normal template.
            'findings' => $order->findings ?? $order->test->report_template,
        ])->save();
    }

    public function saveReport(ImagingOrder $order, User $by, array $data): void
    {
        $this->assertStatus($order, ['performed'], 'edit the report of');

        $order->forceFill([
            'technique' => $data['technique'] ?? null,
            'findings' => $data['findings'] ?? null,
            'impression' => $data['impression'] ?? null,
            'reported_by' => $by->id,
        ])->save();
    }

    public function sign(ImagingOrder $order, User $by): void
    {
        $this->assertStatus($order, ['performed'], 'sign');

        if (blank($order->findings) || blank($order->impression)) {
            throw ValidationException::withMessages(['impression' => 'Findings and impression are required before signing.']);
        }

        $order->forceFill(['status' => 'completed', 'reported_by' => $by->id, 'completed_at' => now()])->save();
        Audit::log('imaging_report_signed', "Imaging report {$order->order_number} signed and released", $order);

        app(SmsNotifier::class)->resultsReady($order->patient, 'imaging', $order->order_number);
    }

    public function attach(ImagingOrder $order, UploadedFile $file, User $by, ?string $caption): ImagingAttachment
    {
        $this->assertStatus($order, ['requested', 'scheduled', 'performed'], 'add images to');

        $path = $file->storeAs(
            'imaging/'.$order->id,
            Str::random(32).'.'.$file->guessExtension(),
            ImagingAttachment::DISK
        );

        $attachment = new ImagingAttachment([
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'caption' => $caption,
        ]);
        $attachment->uploaded_by = $by->id;

        return $order->attachments()->save($attachment);
    }

    public function detach(ImagingAttachment $attachment): void
    {
        $this->assertStatus($attachment->order, ['requested', 'scheduled', 'performed'], 'remove images from');

        Storage::disk(ImagingAttachment::DISK)->delete($attachment->path);
        $attachment->delete();
    }

    protected function assertStatus(ImagingOrder $order, array $allowed, string $action): void
    {
        if (! in_array($order->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot {$action} {$order->order_number} while it is \"{$order->statusLabel()}\".",
            ]);
        }
    }
}
