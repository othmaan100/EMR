<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlinePayment extends Model
{
    public const STATUSES = [
        'pending' => ['label' => 'Waiting for payment', 'color' => 'warning'],
        'success' => ['label' => 'Paid', 'color' => 'success'],
        'failed' => ['label' => 'Failed / abandoned', 'color' => 'secondary'],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['bill_item_ids' => 'array', 'amount' => 'float', 'paid_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
