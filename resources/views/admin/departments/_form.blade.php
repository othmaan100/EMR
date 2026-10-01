<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <x-form.input name="name" label="Department name" :value="$department->name" required col="col-md-8" autofocus placeholder="e.g. Outpatient Department" />
            <x-form.input name="code" label="Code" :value="$department->code" required col="col-md-4" maxlength="20" placeholder="e.g. OPD" help="Short unique code." />
            <x-form.select name="type" label="Type" :options="array_combine(config('emr.department_types'), config('emr.department_types'))" :value="$department->type" required placeholder="Select..." col="col-md-6" />
            <x-form.select name="head_id" label="Head of department" :options="$heads->mapWithKeys(fn ($u) => [$u->id => $u->name.($u->designation ? ' — '.$u->designation : '')])->all()" :value="$department->head_id" placeholder="— None —" col="col-md-6" />
            <x-form.input name="location" label="Location" :value="$department->location" col="col-md-6" placeholder="e.g. Block B, Ground floor" />
            <x-form.input name="phone_extension" label="Phone extension" :value="$department->phone_extension" col="col-md-6" />
            <div class="col-12">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" rows="3" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $department->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $department->is_active))>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>
        </div>
    </div>
</div>
