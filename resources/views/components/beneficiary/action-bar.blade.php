@props(['title', 'subtitle'])
{{-- Figma 329:3436 action bar: gradient pill, title + hint on the left, white pill button on the right. --}}
<div {{ $attributes->class('bg-brand-bar flex h-[59px] items-center justify-between rounded-[30px] pr-[11px] pl-[24px] text-white') }}>
    <div class="flex flex-col">
        <span class="text-[20px] font-bold leading-[22px]">{{ $title }}</span>
        <span class="text-[13px] font-medium leading-[15px] text-white/90">{{ $subtitle }}</span>
    </div>
    {{ $slot }}
</div>
