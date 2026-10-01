{{-- Dose status badge: icon + word, never colour alone. --}}
@switch($status)
    @case('given')<span class="badge text-bg-success"><i class="bi bi-check-lg"></i> Given</span>@break
    @case('due')<span class="badge text-bg-warning"><i class="bi bi-alarm"></i> Due now</span>@break
    @case('overdue')<span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle"></i> Overdue</span>@break
    @case('upcoming')<span class="badge text-bg-light border"><i class="bi bi-calendar"></i> Upcoming</span>@break
    @default<span class="badge text-bg-secondary">No date of birth</span>
@endswitch
