<form method="POST" action="{{ route('account.password.update') }}">
    @csrf
    @method('PUT')
    @foreach (['current_password' => 'Current password', 'password' => 'New password', 'password_confirmation' => 'Confirm new password'] as $field => $label)
        <div class="mb-3">
            <label for="{{ $field }}" class="form-label">{{ $label }}</label>
            <input type="password" id="{{ $field }}" name="{{ $field }}" required
                   autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}"
                   @class(['form-control', 'is-invalid' => $errors->password->has($field)])>
            @if ($errors->password->has($field))<div class="invalid-feedback">{{ $errors->password->first($field) }}</div>@endif
            @if ($field === 'password')<div class="form-text">At least 8 characters with letters and numbers.</div>@endif
        </div>
    @endforeach
    <button class="btn btn-primary {{ $block ?? false ? 'w-100' : '' }}"><i class="bi bi-shield-lock me-1"></i> Change password</button>
</form>
