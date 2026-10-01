<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * An instrument (or its middleware) allowed to post results to the API.
 * Its system account appears as "entered by"; a scientist still verifies.
 */
class LabAnalyzer extends Model
{
    protected $fillable = ['name', 'code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_message_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(LabAnalyzerMapping::class)->orderBy('analyzer_code');
    }

    /**
     * Create the analyser with its (inactive, sign-in-proof) system account.
     * Returns [analyzer, plain token] — the token is shown once.
     */
    public static function register(string $name, string $code): array
    {
        return DB::transaction(function () use ($name, $code) {
            $code = Str::upper($code);
            $user = new User;
            $user->forceFill([
                'name' => "{$name} (analyser)",
                'username' => 'analyser-'.Str::lower($code),
                'email' => 'analyser-'.Str::lower($code).'@system.invalid',
                'password' => Str::random(64),
                'is_active' => false, // can never sign in
                'designation' => 'Laboratory analyser',
            ])->save();

            $analyzer = new self(['name' => $name, 'code' => $code, 'is_active' => true]);
            $analyzer->user_id = $user->id;
            $token = $analyzer->newToken();
            $analyzer->save();

            return [$analyzer, $token];
        });
    }

    public function newToken(): string
    {
        $token = 'lab_'.Str::random(40);
        $this->token_hash = hash('sha256', $token);
        $this->token_hint = substr($token, -4);

        return $token;
    }

    public static function findByToken(?string $token): ?self
    {
        return $token ? self::where('token_hash', hash('sha256', $token))->where('is_active', true)->first() : null;
    }
}
