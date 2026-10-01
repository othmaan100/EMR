<?php

namespace App\Imports;

use App\Imports\Concerns\Lookups;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class StaffImporter extends Importer
{
    use Lookups;

    public function key(): string
    {
        return 'staff';
    }

    public function title(): string
    {
        return 'Staff accounts';
    }

    public function group(): string
    {
        return 'People';
    }

    public function description(): string
    {
        return 'Doctors, nurses, records officers, lab scientists and all other staff, with their roles and department.';
    }

    public function dependsOn(): array
    {
        return ['departments'];
    }

    public function permission(): ?string
    {
        return 'users.manage';
    }

    public function notes(): array
    {
        return [
            'Every imported account must change its password at first sign-in.',
            'Put several roles in one cell separated by commas, e.g. "Doctor, Anaesthetist". Role names must match Administration → Roles exactly.',
            'The Super Admin role cannot be given by import.',
            'Leave "password" blank to use the temporary password entered on the upload page. Passwords in the file are never shown again.',
        ];
    }

    public function matchDescription(): string
    {
        return 'Rows are matched by username. Updating changes details, department and roles; a password is only changed if the file gives one.';
    }

    public function options(): array
    {
        return [
            'default_password' => ['Temporary password for new accounts without one', 'password', ['nullable', 'string', Password::defaults()],
                'Tell staff this password; they must change it when they first sign in.'],
        ];
    }

    public function columns(): array
    {
        $roles = Role::where('name', '!=', config('emr.super_admin_role'))->orderBy('name')->pluck('name')->all();

        return [
            new ImportColumn('name', 'Full name', true, ['string', 'max:150'], 'Dr Aisha Musa', null, null, 'Grace Okon'),
            new ImportColumn('username', 'Username', true, ['alpha_dash', 'min:3', 'max:50'], 'amusa', 'Letters, numbers, - and _ only.', null, 'gokon'),
            new ImportColumn('roles', 'Role(s)', true, ['string', 'max:255'], 'Doctor', 'One or more role names, comma separated.', $roles, 'Records Officer'),
            new ImportColumn('email', 'Email', true, ['email', 'max:255'], 'amusa@hospital.ng', 'Each account needs its own email (used to sign in and to reset passwords).', null, 'gokon@hospital.ng'),
            new ImportColumn('phone', 'Phone', false, ['string', 'max:30'], '08031230000'),
            new ImportColumn('staff_id', 'Staff ID', false, ['string', 'max:50'], 'GHP/0045', null, null, 'GHP/0112'),
            new ImportColumn('designation', 'Designation / job title', false, ['string', 'max:100'], 'Medical Officer', null, null, 'Health Records Officer'),
            new ImportColumn('department_code', 'Department code', false, ['string'], 'MED', null, null, 'REC'),
            new ImportColumn('password', 'Password', false, ['string', 'min:8', 'max:100'], '', 'Optional — see notes.'),
            new ImportColumn('is_active', 'Active', false, ['in:0,1'], 'yes', 'yes or no (default yes).', ['yes', 'no']),
        ];
    }

    public function normalise(array $row): array
    {
        $row['username'] = is_string($row['username']) ? strtolower($row['username']) : $row['username'];
        $row['email'] = is_string($row['email']) ? strtolower($row['email']) : $row['email'];
        $row['is_active'] = Values::bool($row['is_active']);
        $row['phone'] = Values::phone($row['phone']);

        return $row;
    }

    public function check(array &$row): array
    {
        $errors = [];

        $row['_roles'] = [];
        $allRoles = Role::pluck('name')->all();
        foreach (preg_split('/\s*[,;\/]\s*/', (string) $row['roles'], -1, PREG_SPLIT_NO_EMPTY) as $name) {
            $match = collect($allRoles)->first(fn ($r) => strcasecmp($r, $name) === 0);
            if (! $match) {
                $errors[] = "Role \"{$name}\" does not exist.";
            } elseif ($match === config('emr.super_admin_role')) {
                $errors[] = 'The Super Admin role cannot be given by import.';
            } else {
                $row['_roles'][] = $match;
            }
        }

        $row['_department_id'] = $this->lookup('department', $row['department_code']);
        if ($row['department_code'] && ! $row['_department_id']) {
            $errors[] = "Department \"{$row['department_code']}\" not found — import departments first.";
        }

        $existing = $this->find($row);
        if ($existing?->isSuperAdmin()) {
            $errors[] = 'This username belongs to a Super Admin, who cannot be changed by import.';
        }
        if ($row['email'] && User::where('email', $row['email'])->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
            $errors[] = "Email {$row['email']} is already used by another account.";
        }
        if (! $existing && ! $row['password'] && blank($this->options['default_password_hash'] ?? null)) {
            $errors[] = 'No password: fill the password column or set a temporary password on the upload page.';
        }
        if ($row['password'] && Validator::make(['p' => $row['password']], ['p' => Password::defaults()])->fails()) {
            $errors[] = 'The password is too weak.';
        }

        return $errors;
    }

    public function rowKey(array $row): ?string
    {
        return $row['username'];
    }

    public function find(array $row): mixed
    {
        return User::whereRaw('lower(username) = ?', [strtolower((string) $row['username'])])->first();
    }

    public function save(array $row, mixed $existing): void
    {
        $user = $existing ?? new User;

        foreach (['name', 'username', 'email', 'phone', 'staff_id', 'designation'] as $field) {
            if (! $existing || $row[$field] !== null) {
                $user->{$field} = $row[$field];
            }
        }
        if ($row['_department_id']) {
            $user->department_id = $row['_department_id'];
        }
        if ($row['is_active'] !== null || ! $existing) {
            $user->is_active = $row['is_active'] ?? true;
        }
        if ($row['password']) {
            $user->password = $row['password']; // hashed by the model cast
            $user->must_change_password = true;
        } elseif (! $existing) {
            $user->password = $this->options['default_password_hash']; // already hashed; the cast leaves it alone
            $user->must_change_password = true;
        }

        $user->save();
        $user->syncRoles($row['_roles']);
    }

    /**
     * Only a hash of the temporary password is ever stored with the import.
     */
    public static function prepareOptions(array $options): array
    {
        if (filled($options['default_password'] ?? null)) {
            $options['default_password_hash'] = Hash::make($options['default_password']);
        }
        unset($options['default_password']);

        return $options;
    }
}
