{{-- An encrypted setting: never pre-filled; blank keeps the saved value. --}}
<div class="col-md-6">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    <input type="password" id="{{ $name }}" name="{{ $name }}" autocomplete="new-password" @class(['form-control', 'is-invalid' => $errors->has($name)])
           placeholder="{{ $saved ? '•••••••• saved — leave blank to keep' : 'Not set' }}">
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
