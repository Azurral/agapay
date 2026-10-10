@props(['tone' => 'ok'])
<span {{ $attributes->class([
    'inline-flex h-[39px] w-[155px] items-center justify-center rounded-[10px] border-4 bg-white text-[14px] font-bold leading-[24px]',
    'border-ok' => $tone === 'ok',
    'border-bad' => $tone === 'bad',
    'border-field text-muted' => $tone === 'neutral',
]) }}>{{ $slot }}</span>
