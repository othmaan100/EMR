<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    public const TYPES = [
        'appointment_reminder' => 'Appointment reminder',
        'results_ready' => 'Results ready',
        'immunization_reminder' => 'Immunization reminder',
        'appointment_confirmed' => 'Appointment confirmed',
        'portal_access' => 'Portal access code',
        'payment_link' => 'Payment link',
        'test' => 'Test message',
    ];

    public const STATUSES = [
        'queued' => ['label' => 'Queued', 'color' => 'info'],
        'sent' => ['label' => 'Sent', 'color' => 'success'],
        'failed' => ['label' => 'Failed', 'color' => 'danger'],
        'logged' => ['label' => 'Logged only', 'color' => 'secondary'],
    ];

    protected $fillable = ['patient_id', 'phone', 'body', 'type', 'status', 'dedupe_key', 'created_by'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
