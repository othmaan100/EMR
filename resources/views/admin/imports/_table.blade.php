<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr><th class="ps-3">When</th><th>Type</th><th>File</th><th>By</th><th class="text-end">Added</th><th class="text-end">Updated</th><th class="text-end">Skipped</th><th class="text-end">Errors</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($imports as $imp)
                <tr>
                    <td class="ps-3 small text-nowrap"><a href="{{ route('admin.imports.review', $imp) }}">{{ format_date($imp->created_at, true) }}</a></td>
                    <td class="small">{{ $imp->typeTitle() }}</td>
                    <td class="small text-truncate" style="max-width: 14rem;" title="{{ $imp->original_name }}">{{ $imp->original_name }}</td>
                    <td class="small">{{ $imp->user?->name }}</td>
                    <td class="text-end">{{ $imp->status === 'completed' ? number_format($imp->created_count) : '—' }}</td>
                    <td class="text-end">{{ $imp->status === 'completed' ? number_format($imp->updated_count) : '—' }}</td>
                    <td class="text-end">{{ $imp->status === 'completed' ? number_format($imp->skipped_count) : '—' }}</td>
                    <td @class(['text-end', 'text-danger fw-semibold' => $imp->error_rows > 0])>{{ number_format($imp->error_rows) }}</td>
                    <td><span class="badge text-bg-{{ $imp->statusColor() }}">{{ $imp->statusLabel() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No imports yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
