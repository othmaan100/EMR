<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Accounts that stand for automatic actions (e.g. "PACS link") so records
 * show what really did the work. They are inactive and can never sign in.
 */
final class SystemUser
{
    public static function for(string $key, string $name): User
    {
        $username = 'system-'.Str::slug($key);

        return User::where('username', $username)->first() ?? tap(new User, function (User $user) use ($username, $name) {
            $user->forceFill([
                'name' => $name,
                'username' => $username,
                'email' => $username.'@system.invalid',
                'password' => Str::random(64),
                'is_active' => false,
                'designation' => 'System (automatic)',
            ])->save();
        });
    }
}
