@props(['title', 'open' => false, 'width' => 700])
<div x-data="{ open: {{ $open ? 'true' : 'false' }} }" x-on:open-modal.window="if ($event.detail === '{{ $attributes->get('name') }}') open = true"
     x-on:keydown.escape.window="open = false">
    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-start justify-center pt-[300px]" @click.self="open = false" role="dialog" aria-modal="true" aria-label="{{ $title }}">
        <div class="rounded-[20px] border-[1.5px] border-black/10 bg-white p-[22.5px] shadow-[0_4px_20px_rgba(0,0,0,0.08)]" style="width: {{ $width }}px">
            <div class="flex items-center justify-between">
                <h2 class="text-[16px] font-bold leading-[24px]">{{ $title }}</h2>
                <button type="button" @click="open = false" class="hover-tint flex size-[28px] items-center justify-center rounded-full text-[20px] leading-none text-muted" aria-label="Close">&times;</button>
            </div>
            <div class="mt-[24px]">{{ $slot }}</div>
        </div>
    </div>
</div>
