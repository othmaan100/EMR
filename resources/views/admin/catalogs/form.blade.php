@extends('layouts.app')

@section('title', ($item->exists ? 'Edit ' : 'Add ').$def['singular'])

@section('content')
<form method="POST" action="{{ $item->exists ? route('admin.catalogs.update', [$catalog, $item->id]) : route('admin.catalogs.store', $catalog) }}"
      class="mx-auto" style="max-width: 800px;">
    @csrf
    @if ($item->exists) @method('PUT') @endif
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                @foreach ($def['fields'] as $name => $field)
                    @if ($field['type'] === 'textarea')
                        <x-form.textarea :name="$name" :label="$field['label']" :value="$item->{$name}" :col="$field['col']" rows="8" />
                    @elseif ($field['type'] === 'select')
                        <x-form.select :name="$name" :label="$field['label']" :options="array_is_list($field['options']) ? array_combine($field['options'], $field['options']) : $field['options']"
                                       :value="$item->{$name}" placeholder="—" :col="$field['col']" />
                    @else
                        <x-form.input :name="$name" :label="$field['label']" :type="$field['type'] === 'number' ? 'number' : 'text'"
                                      :value="$item->{$name}" :required="in_array('required', $field['rules'], true)" :col="$field['col']"
                                      :list="$field['type'] === 'datalist' ? 'dl-'.$name : null" />
                        @if ($field['type'] === 'datalist')
                            <datalist id="dl-{{ $name }}">@foreach ($field['options'] as $opt)<option value="{{ $opt }}"></option>@endforeach</datalist>
                        @endif
                    @endif
                @endforeach
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $item->is_active))>
                        <label class="form-check-label" for="is_active">Active (available for ordering)</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admin.catalogs.index', $catalog) }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save</button>
    </div>
</form>
@endsection
