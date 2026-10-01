<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StoreMovement extends Model
{
    public const TYPES = [
        'receipt' => 'Received',
        'issue' => 'Issued',
        'return' => 'Returned to store',
        'adjustment' => 'Stock-count adjustment',
        'write_off' => 'Written off (damaged / expired)',
    ];

    protected $fillable = ['store_item_id', 'type', 'quantity', 'unit_cost', 'balance_after', 'department_id', 'reason', 'user_id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'balance_after' => 'integer', 'unit_cost' => 'float'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StoreItem::class, 'store_item_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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
