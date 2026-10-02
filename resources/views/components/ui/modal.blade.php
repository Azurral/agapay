@props(['title', 'open' => false, 'width' => 700])
<div x-data="{ open: {{ $open ? 'true' : 'false' }} }" x-on:open-modal.window="if ($event.detail === '{{ $attributes->get('name') }}') open = true"
     x-on:keydown.escape.window="open = false">
    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-start justify-center pt-[300px]" @click.self="open = false" role="dialog" aria-modal="true" aria-label="{{ $title }}">
        {{-- Brand gradient outline (same as the pill buttons) + deep purple-tinted shadow so modals stand out. --}}
        <div class="border-gradient rounded-[20px] p-[22.5px] shadow-[0_20px_50px_rgba(90,93,227,0.25),0_4px_12px_rgba(0,0,0,0.08)]" style="width: {{ $width }}px">
            <div class="flex items-center justify-between">
                <h2 class="text-[16px] font-bold leading-[24px]">{{ $title }}</h2>
                <button type="button" @click="open = false" class="hover-tint flex size-[28px] items-center justify-center rounded-full text-[20px] leading-none text-muted" aria-label="Close">&times;</button>
            </div>
            <div class="mt-[24px]">{{ $slot }}</div>
        </div>
    </div>
</div>
