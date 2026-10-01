<?php

namespace App\Models;

use App\Imports\ImportRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataImport extends Model
{
    public const STATUSES = [
        'validated' => ['label' => 'Checked — waiting to import', 'color' => 'warning'],
        'completed' => ['label' => 'Imported', 'color' => 'success'],
        'failed' => ['label' => 'Failed', 'color' => 'danger'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'secondary'],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['options' => 'array', 'errors' => 'array', 'warnings' => 'array', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeTitle(): string
    {
        return ImportRegistry::find($this->type)?->title() ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'secondary';
    }

    public function importableRows(): int
    {
        return $this->create_rows + $this->update_rows;
    }
}
