@php($granted = old('permissions', $granted))

<div class="card mb-3">
    <div class="card-body">
        @if ($isSystem)
            <label class="form-label">Role name</label>
            <input type="text" class="form-control" value="{{ $role->name }}" disabled>
            <div class="form-text">Built-in role names cannot be changed.</div>
        @else
            <x-form.input name="name" label="Role name" :value="$role->name" required autofocus placeholder="e.g. Physiotherapist" />
        @endif
    </div>
</div>

@foreach (config('emr.permissions') as $group => $permissions)
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            {{ $group }}
            <div class="form-check mb-0 fw-normal small">
                <input class="form-check-input" type="checkbox" id="group-{{ Str::slug($group) }}" data-check-group="{{ Str::slug($group) }}"
                       @checked(empty(array_diff(array_keys($permissions), $granted)))>
                <label class="form-check-label" for="group-{{ Str::slug($group) }}">Select all</label>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2">
                @foreach ($permissions as $name => $label)
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $name }}" id="perm-{{ $name }}"
                                   data-group="{{ Str::slug($group) }}" @checked(in_array($name, $granted, true))>
                            <label class="form-check-label" for="perm-{{ $name }}">
                                {{ $label }} <code class="small text-muted ms-1">{{ $name }}</code>
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endforeach
@error('permissions.*')<div class="alert alert-danger">{{ $message }}</div>@enderror

<script>
    document.addEventListener('change', (e) => {
        const group = e.target.dataset.checkGroup;
        if (group) {
            document.querySelectorAll(`[data-group="${group}"]`).forEach((cb) => (cb.checked = e.target.checked));
        }
    });
</script>
