@props(['report'])
{{-- Figma 423:166 status chip: green Validated, pink For Validation. --}}
<span {{ $attributes->class([
    'inline-flex h-[34px] w-[140px] items-center justify-center rounded-[10px] border-3 bg-white text-[13px] font-bold leading-[20px]',
    'border-ok' => $report->statusTone() === 'ok',
    'border-bad' => $report->statusTone() === 'bad',
]) }}>{{ $report->trashed() ? 'Archived' : $report->statusLabel() }}</span>
