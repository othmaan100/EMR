<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'role', 'department_id', 'status']);

        $users = User::with(['department', 'roles'])
            ->where('email', 'not like', '%@system.invalid') // analyser / automation accounts
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%$v%")
                ->orWhere('username', 'like', "%$v%")
                ->orWhere('email', 'like', "%$v%")
                ->orWhere('staff_id', 'like', "%$v%")))
            ->when($filters['role'] ?? null, fn ($q, $v) => $q->whereHas('roles', fn ($q) => $q->where('name', $v)))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('is_active', $filters['status'] === 'active'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::orderBy('name')->pluck('name'),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', $this->formData(new User(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create($data + [
                'is_active' => true,
                'must_change_password' => $request->boolean('must_change_password', true),
            ]);
            $user->syncRoles($data['roles']);

            return $user;
        });

        Audit::log('roles_assigned', "Roles for {$user->name}: ".implode(', ', $data['roles']), $user);

        return redirect()->route('admin.users.show', $user)->with('success', "Staff account for {$user->name} created.");
    }

    public function show(Request $request, User $user): View
    {
        return view('admin.users.show', [
            'user' => $user->load(['department', 'roles']),
            'activity' => $request->user()->can('audit.view')
                ? AuditLog::where('user_id', $user->id)->latest('id')->limit(10)->get()
                : collect(),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        $this->guardSuperAdminAccount($request, $user);

        return view('admin.users.edit', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guardSuperAdminAccount($request, $user);
        $data = $this->validated($request, $user);

        $superAdmin = config('emr.super_admin_role');
        if ($user->isSuperAdmin() && ! in_array($superAdmin, $data['roles'], true) && $this->isLastSuperAdmin($user)) {
            throw ValidationException::withMessages(['roles' => 'This is the only active Super Admin; the role cannot be removed.']);
        }

        $oldRoles = $user->getRoleNames()->sort()->values()->all();

        DB::transaction(function () use ($user, $data) {
            $user->update($data);
            $user->syncRoles($data['roles']);
        });

        $newRoles = collect($data['roles'])->sort()->values()->all();
        if ($oldRoles !== $newRoles) {
            Audit::log('roles_assigned', "Roles for {$user->name} changed", $user, ['roles' => implode(', ', $oldRoles)], ['roles' => implode(', ', $newRoles)]);
        }

        return redirect()->route('admin.users.show', $user)->with('success', 'Staff details updated.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->guardSuperAdminAccount($request, $user);

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->is_active && $user->isSuperAdmin() && $this->isLastSuperAdmin($user)) {
            return back()->with('error', 'This is the only active Super Admin and cannot be deactivated.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->name.' has been '.($user->is_active ? 'activated' : 'deactivated').'.');
    }

    public function unlock(Request $request, User $user): RedirectResponse
    {
        $this->guardSuperAdminAccount($request, $user);

        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->saveQuietly();
        Audit::log('account_unlocked', "Account \"{$user->username}\" unlocked", $user);

        return back()->with('success', "{$user->name} can sign in again.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->guardSuperAdminAccount($request, $user);

        $request->validateWithBag('resetPassword', [
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $request->input('password'),
            'must_change_password' => true,
            'remember_token' => null,
        ])->save();

        Audit::log('password_reset', "Password reset for {$user->name}", $user);

        return back()->with('success', "Password reset. {$user->name} must choose a new password at next sign-in.");
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:50', Rule::unique('users')->ignore($user)],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'staff_id' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($user)],
            'designation' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in($this->assignableRoles($request)->all())],
            'password' => [$user ? 'exclude' : 'required', 'confirmed', Password::defaults()],
        ]);

        unset($data['password_confirmation']);

        return $data;
    }

    /**
     * Only a Super Admin may grant the Super Admin role.
     */
    protected function assignableRoles(Request $request): Collection
    {
        return Role::orderBy('name')
            ->when(! $request->user()->isSuperAdmin(), fn ($q) => $q->where('name', '!=', config('emr.super_admin_role')))
            ->pluck('name');
    }

    /**
     * Stops administrators from editing (e.g. resetting the password of) a
     * Super Admin account, which would let them take it over.
     */
    protected function guardSuperAdminAccount(Request $request, User $user): void
    {
        abort_if($user->isSuperAdmin() && ! $request->user()->isSuperAdmin(), 403, 'Only a Super Admin can modify this account.');
    }

    protected function isLastSuperAdmin(User $user): bool
    {
        return ! User::role(config('emr.super_admin_role'))->active()->whereKeyNot($user->id)->exists();
    }

    protected function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => $this->assignableRoles(request()),
            'departments' => Department::active()->orWhere('id', $user->department_id)->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
