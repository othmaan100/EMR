<?php

namespace App\Support;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Patient photos are protected health information, so they live on the
 * private "local" disk and are only served through an authorised route.
 */
class PatientPhoto
{
    public const DISK = 'local';

    public function apply(Request $request, Patient $patient): void
    {
        $new = null;

        if ($request->file('photo') instanceof UploadedFile) {
            $file = $request->file('photo');
            $new = $file->storeAs('patients/photos', Str::random(32).'.'.$file->guessExtension(), self::DISK);
        } elseif ($request->filled('photo_data')) {
            $new = $this->storeDataUrl($request->input('photo_data'));
        } elseif (! $request->boolean('remove_photo')) {
            return;
        }

        $old = $patient->photo;
        $patient->forceFill(['photo' => $new])->saveQuietly();

        if ($old) {
            Storage::disk(self::DISK)->delete($old);
        }
    }

    /**
     * Webcam captures arrive as a base64 data URL.
     */
    protected function storeDataUrl(string $dataUrl): string
    {
        $binary = base64_decode(Str::after($dataUrl, ','), true);
        $info = $binary ? @getimagesizefromstring($binary) : false;

        if (! $info || ! in_array($info['mime'], ['image/jpeg', 'image/png'], true)) {
            throw ValidationException::withMessages(['photo' => 'The captured photo could not be read. Please try again.']);
        }

        $path = 'patients/photos/'.Str::random(32).($info['mime'] === 'image/png' ? '.png' : '.jpg');
        Storage::disk(self::DISK)->put($path, $binary);

        return $path;
    }
}
