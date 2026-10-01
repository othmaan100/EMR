<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabOrder extends Model
{
    use Auditable;

    public const STATUSES = [
        'requested' => ['label' => 'Requested', 'color' => 'primary'],
        'collected' => ['label' => 'Sample collected', 'color' => 'info'],
        'in_progress' => ['label' => 'Awaiting verification', 'color' => 'warning'],
        'completed' => ['label' => 'Results ready', 'color' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'dark'],
    ];

    protected $fillable = ['priority', 'clinical_notes'];

    protected function casts(): array
    {
        return ['collected_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function isReleased(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Released items that carry at least one abnormal flag.
     */
    public function abnormalCount(): int
    {
        return $this->items->filter(fn (LabOrderItem $i) => $i->status === 'verified' && $i->hasAbnormal())->count();
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
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
