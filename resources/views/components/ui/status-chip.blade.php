@props(['tone' => 'ok', 'pulse' => false, 'pop' => false])
{{-- pulse: a slowly pulsing dot for something still waiting; pop: a quick bounce for the chip that just changed. --}}
<span {{ $attributes->class([
    'inline-flex h-[39px] w-[155px] items-center justify-center rounded-[10px] border-4 bg-white text-[14px] font-bold leading-[24px]',
    'border-ok' => $tone === 'ok',
    'border-bad' => $tone === 'bad',
    'border-field text-muted' => $tone === 'neutral',
    'chip-pop' => $pop,
]) }}>@if ($pulse)<span class="chip-dot" aria-hidden="true"></span>@endif{{ $slot }}</span>
