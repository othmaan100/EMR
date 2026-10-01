<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requisition extends Model
{
    public const STATUSES = [
        'submitted' => ['label' => 'Waiting for store', 'color' => 'warning'],
        'partially_issued' => ['label' => 'Partly issued', 'color' => 'info'],
        'issued' => ['label' => 'Issued', 'color' => 'success'],
        'rejected' => ['label' => 'Rejected', 'color' => 'danger'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'secondary'],
    ];

    public const OPEN = ['submitted', 'partially_issued'];

    protected $fillable = ['department_id', 'needed_by', 'notes'];

    protected function casts(): array
    {
        return ['needed_by' => 'date', 'issued_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class)->orderBy('id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
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
