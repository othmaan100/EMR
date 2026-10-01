<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'receipt' => ['label' => 'Received', 'color' => 'success'],
        'dispense' => ['label' => 'Dispensed', 'color' => 'primary'],
        'adjustment' => ['label' => 'Adjustment', 'color' => 'warning'],
        'disposal' => ['label' => 'Disposal', 'color' => 'danger'],
    ];

    protected $fillable = ['drug_id', 'stock_batch_id', 'type', 'quantity', 'reason', 'user_id'];

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
