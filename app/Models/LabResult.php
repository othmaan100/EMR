<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabResult extends Model
{
    public const FLAGS = [
        'low' => ['label' => 'Low', 'short' => 'L', 'class' => 'text-info-emphasis fw-bold', 'icon' => 'bi-arrow-down'],
        'high' => ['label' => 'High', 'short' => 'H', 'class' => 'text-danger fw-bold', 'icon' => 'bi-arrow-up'],
        'abnormal' => ['label' => 'Abnormal', 'short' => 'A', 'class' => 'text-danger fw-bold', 'icon' => 'bi-exclamation-circle'],
    ];

    protected $fillable = ['lab_test_parameter_id', 'name', 'unit', 'reference', 'value', 'numeric_value', 'flag'];

    protected function casts(): array
    {
        return ['numeric_value' => 'float'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LabOrderItem::class, 'lab_order_item_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(LabTestParameter::class, 'lab_test_parameter_id');
    }
}
