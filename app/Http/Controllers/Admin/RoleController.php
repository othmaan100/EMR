<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get(),
            'systemRoles' => array_keys(config('emr.roles')),
            'totalPermissions' => Permission::count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', ['role' => new Role, 'granted' => [], 'isSystem' => false]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')],
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'name')],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        Audit::log('role_created', "Role \"{$role->name}\" created", $role, null, ['permissions' => implode(', ', $data['permissions'] ?? [])]);

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$role->name}\" created.");
    }

    public function edit(Role $role): View
    {
        $this->guardSuperAdminRole($role);

        return view('admin.roles.edit', [
            'role' => $role,
            'granted' => $role->permissions->pluck('name')->all(),
            'isSystem' => $this->isSystemRole($role),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->guardSuperAdminRole($role);
        $isSystem = $this->isSystemRole($role);

        $data = $request->validate([
            'name' => [$isSystem ? 'exclude' : 'required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role)],
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'name')],
        ]);

        $old = $role->permissions->pluck('name')->sort()->values()->all();
        $new = collect($data['permissions'] ?? [])->sort()->values()->all();

        if (! $isSystem) {
            $role->update(['name' => $data['name']]);
        }
        $role->syncPermissions($new);

        if ($old !== $new) {
            Audit::log('role_updated', "Permissions for role \"{$role->name}\" changed", $role,
                ['permissions' => implode(', ', $old)], ['permissions' => implode(', ', $new)]);
        }

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$role->name}\" updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($this->isSystemRole($role)) {
            return back()->with('error', 'Built-in roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', "\"{$role->name}\" is assigned to staff. Reassign them before deleting.");
        }

        $role->delete();
        Audit::log('role_deleted', "Role \"{$role->name}\" deleted");

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$role->name}\" deleted.");
    }

    protected function isSystemRole(Role $role): bool
    {
        return array_key_exists($role->name, config('emr.roles'));
    }

    protected function guardSuperAdminRole(Role $role): void
    {
        abort_if($role->name === config('emr.super_admin_role'), 403, 'Super Admin always has every permission.');
    }
}
