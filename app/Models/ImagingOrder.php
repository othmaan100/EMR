<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImagingOrder extends Model
{
    use Auditable;

    public const STATUSES = [
        'requested' => ['label' => 'Requested', 'color' => 'primary'],
        'scheduled' => ['label' => 'Scheduled', 'color' => 'info'],
        'performed' => ['label' => 'Awaiting report', 'color' => 'warning'],
        'completed' => ['label' => 'Report ready', 'color' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'dark'],
    ];

    protected $fillable = ['imaging_test_id', 'priority', 'clinical_notes'];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'performed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(ImagingTest::class, 'imaging_test_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ImagingAttachment::class)->oldest();
    }

    public function isReleased(): bool
    {
        return $this->status === 'completed';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'secondary';
    }
}
