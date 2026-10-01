{{--
    Thumbnails of an imaging order's files.
    @param ImagingOrder $order
    @param bool $removable
--}}
@if ($order->attachments->isNotEmpty())
    <div class="d-flex flex-wrap gap-2">
        @foreach ($order->attachments as $file)
            <div class="border rounded p-1 text-center bg-white" style="width: 140px;">
                <a href="{{ route('radiology.attachment', $file) }}" target="_blank" class="d-block text-decoration-none" title="{{ $file->original_name }}">
                    @if ($file->isImage())
                        <img src="{{ route('radiology.attachment', $file) }}" alt="{{ $file->caption ?? $file->original_name }}" loading="lazy"
                             style="width: 130px; height: 100px; object-fit: cover;" class="rounded bg-dark">
                    @else
                        <div class="d-flex align-items-center justify-content-center bg-light rounded" style="height: 100px;">
                            <i class="bi bi-file-earmark-pdf fs-1 text-danger"></i>
                        </div>
                    @endif
                    <div class="small text-truncate mt-1">{{ $file->caption ?: $file->original_name }}</div>
                </a>
                @if ($removable ?? false)
                    <form method="POST" action="{{ route('radiology.detach', $file) }}" onsubmit="return confirm('Remove this file?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-link btn-sm text-danger p-0">Remove</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@else
    <p class="text-muted small mb-0">No images attached.</p>
@endif
