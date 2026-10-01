<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Audit
{
    protected static bool $muted = false;

    /**
     * Run a bulk operation (data import) without one audit row per record;
     * the caller logs a single summary entry instead.
     */
    public static function muted(callable $callback): mixed
    {
        $previous = self::$muted;
        self::$muted = true;
        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }

    public static function log(
        string $event,
        ?string $description = null,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
    ): AuditLog {
        if (self::$muted) {
            return new AuditLog; // not saved
        }

        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'description' => $description ? Str::limit($description, 250) : null,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 490) : null,
            'url' => $request ? Str::limit($request->fullUrl(), 490) : null,
        ]);
    }
}
