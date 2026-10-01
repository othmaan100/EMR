@extends('layouts.app')

@section('title', $def['title'])

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <ul class="nav nav-pills">
        @foreach (['services' => 'Services', 'lab-tests' => 'Lab Tests', 'imaging' => 'Imaging', 'drugs' => 'Drug Formulary', 'vaccines' => 'Vaccines', 'procedures' => 'Procedures', 'theatres' => 'Theatres', 'store-items' => 'Store Items'] as $key => $label)
            @if (auth()->user()->can($key === 'store-items' ? 'stores.manage' : 'catalog.manage'))
                <li class="nav-item"><a @class(['nav-link py-1', 'active' => $catalog === $key]) href="{{ route('admin.catalogs.index', $key) }}">{{ $label }}</a></li>
            @endif
        @endforeach
    </ul>
    <div class="d-flex gap-2">
        <form method="GET"><input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Search…" aria-label="Search"></form>
        <a href="{{ route('admin.catalogs.create', $catalog) }}" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg me-1"></i> Add {{ $def['singular'] }}</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    @foreach (collect($def['fields'])->reject(fn ($f) => $f['hidden_in_list'] ?? false) as $field)<th @class(['ps-3' => $loop->first])>{{ $field['label'] }}</th>@endforeach
                    <th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr @class(['text-muted' => ! $item->is_active])>
                        @foreach (collect($def['fields'])->reject(fn ($f) => $f['hidden_in_list'] ?? false) as $name => $field)
                            <td @class(['ps-3' => $loop->first, 'fw-semibold' => $name === 'name'])>{{ $item->{$name} ?? '—' }}</td>
                        @endforeach
                        <td><span class="badge {{ $item->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end pe-3 text-nowrap">
                            @if ($catalog === 'lab-tests')
                                <a href="{{ route('admin.lab-parameters.index', $item->id) }}" class="btn btn-sm btn-light" title="Result fields & reference ranges">
                                    <i class="bi bi-list-ol"></i> {{ $item->parameters_count }}
                                </a>
                            @endif
                            <a href="{{ route('admin.catalogs.edit', [$catalog, $item->id]) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($def['fields']) + 2 }}" class="text-center text-muted py-4">Nothing found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($items->hasPages())<div class="card-footer bg-white">{{ $items->links() }}</div>@endif
</div>
@endsection
