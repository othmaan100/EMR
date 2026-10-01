<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Syncs roles & permissions from config/emr.php. Safe to re-run: it only
 * adds missing permissions to roles and never removes custom assignments.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (collect(config('emr.permissions'))->flatMap(fn ($group) => array_keys($group)) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (config('emr.roles') as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $missing = array_diff($permissions, $role->permissions->pluck('name')->all());

            if ($missing) {
                $role->givePermissionTo($missing);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
