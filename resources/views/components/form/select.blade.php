@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'help' => null, 'col' => null, 'placeholder' => null])

{{-- $options: [value => label] --}}
<div @class([$col, 'mb-3' => ! $col])>
    <label for="{{ $name }}" class="form-label">
        {{ $label }} @if ($required)<span class="text-danger">*</span>@endif
    </label>
    <select id="{{ $name }}" name="{{ $name }}" @required($required)
            {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
        @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) old($name, $value) === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
