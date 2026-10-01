{{-- Formats one report value. @param mixed $value  @param string $format --}}
@switch($format)
    @case('money'){{ $value === null ? '—' : money($value) }}@break
    @case('int'){{ $value === null ? '—' : number_format((float) $value) }}@break
    @case('dec1'){{ $value === null ? '—' : number_format((float) $value, 1) }}@break
    @case('pct'){{ $value === null ? '—' : number_format((float) $value, 1).'%' }}@break
    @case('date'){{ $value ? format_date($value) : '—' }}@break
    @default{{ $value === null || $value === '' ? '—' : $value }}
@endswitch
