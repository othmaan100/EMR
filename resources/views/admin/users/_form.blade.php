@php($selectedRoles = old('roles', $user->exists ? $user->getRoleNames()->all() : []))

<div class="card mb-3">
    <div class="card-header">Personal details</div>
    <div class="card-body">
        <div class="row g-3">
            <x-form.input name="name" label="Full name" :value="$user->name" required col="col-md-6" autofocus />
            <x-form.input name="phone" type="tel" label="Phone" :value="$user->phone" col="col-md-6" />
            <x-form.input name="email" type="email" label="Email" :value="$user->email" required col="col-md-6" />
            <x-form.input name="username" label="Username" :value="$user->username" required col="col-md-6" autocomplete="off" help="Used to sign in. Letters, numbers, dashes and underscores." />
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Employment</div>
    <div class="card-body">
        <div class="row g-3">
            <x-form.input name="staff_id" label="Staff ID / Employee no." :value="$user->staff_id" col="col-md-4" />
            <x-form.input name="designation" label="Designation / Job title" :value="$user->designation" col="col-md-4" placeholder="e.g. Senior Registrar" />
            <x-form.select name="department_id" label="Department" :options="$departments->all()" :value="$user->department_id" placeholder="— None —" col="col-md-4" />
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Roles <span class="text-danger">*</span></div>
    <div class="card-body">
        <p class="text-muted small">Roles decide what this person can see and do. Select at least one.</p>
        <div class="row g-2">
            @foreach ($roles as $role)
                <div class="col-sm-6 col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role }}" id="role-{{ Str::slug($role) }}"
                               @checked(in_array($role, $selectedRoles, true))>
                        <label class="form-check-label" for="role-{{ Str::slug($role) }}">{{ $role }}</label>
                    </div>
                </div>
            @endforeach
        </div>
        @error('roles')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        @error('roles.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>
</div>

@unless ($user->exists)
    <div class="card mb-3">
        <div class="card-header">Sign-in password</div>
        <div class="card-body">
            <div class="row g-3">
                <x-form.input name="password" type="password" label="Temporary password" required col="col-md-6" autocomplete="new-password" help="At least 8 characters with letters and numbers." />
                <x-form.input name="password_confirmation" type="password" label="Confirm password" required col="col-md-6" autocomplete="new-password" />
            </div>
            <input type="hidden" name="must_change_password" value="0">
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="must_change_password" value="1" id="must_change_password" @checked(old('must_change_password', '1'))>
                <label class="form-check-label" for="must_change_password">Require a password change at first sign-in (recommended)</label>
            </div>
        </div>
    </div>
@endunless
