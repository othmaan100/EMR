<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabOrderItem extends Model
{
    protected $fillable = ['lab_test_id', 'status', 'comment'];

    protected function casts(): array
    {
        return ['entered_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class)->orderBy('id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function hasAbnormal(): bool
    {
        return $this->results->contains(fn (LabResult $r) => $r->flag !== null);
    }
}
