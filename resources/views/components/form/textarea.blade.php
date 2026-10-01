@props(['name', 'label', 'value' => null, 'required' => false, 'help' => null, 'col' => null, 'rows' => 3])

<div @class([$col, 'mb-3' => ! $col])>
    <label for="{{ $name }}" class="form-label">
        {{ $label }} @if ($required)<span class="text-danger">*</span>@endif
    </label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
              {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
