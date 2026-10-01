<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class IntegrationMessage extends Model
{
    public const UPDATED_AT = null;

    public const CHANNELS = [
        'lab' => 'Lab analysers',
        'pacs' => 'PACS / DICOM',
        'payment' => 'Online payments',
        'claims' => 'NHIA / HMO e-claims',
        'nin' => 'National ID (NIN)',
    ];

    protected $fillable = ['channel', 'direction', 'status', 'reference', 'summary', 'payload', 'user_id'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Log one exchange. Payloads are truncated; callers never pass secrets.
     */
    public static function record(string $channel, string $direction, string $status, string $summary, ?string $reference = null, ?string $payload = null, ?Model $subject = null): self
    {
        $message = new self([
            'channel' => $channel,
            'direction' => $direction,
            'status' => $status,
            'reference' => $reference ? Str::limit($reference, 95, '') : null,
            'summary' => Str::limit($summary, 490),
            'payload' => $payload !== null ? Str::limit($payload, 20000) : null,
            'user_id' => auth()->id(),
        ]);
        $message->subject()->associate($subject);
        $message->save();

        return $message;
    }
}
