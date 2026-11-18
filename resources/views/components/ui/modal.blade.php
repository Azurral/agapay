@props(['title', 'open' => false, 'width' => 700, 'top' => 300])
{{-- width: pixels, or 'wide' (the whole 1600px design width less a margin, for big tables). The window scrolls if it is taller than the screen. --}}
<div x-data="{ open: {{ $open ? 'true' : 'false' }} }" x-on:open-modal.window="if ($event.detail === '{{ $attributes->get('name') }}') open = true"
     x-on:keydown.escape.window="open = false">
    <div x-cloak x-show="open" class="modal-area fixed inset-0 z-50 flex items-start justify-center overflow-y-auto pb-[40px]" style="--modal-top: {{ (int) $top }}px"
         @click.self="open = false" role="dialog" aria-modal="true" aria-label="{{ $title }}">
        {{-- Brand gradient outline (same as the pill buttons) + deep purple-tinted shadow so modals stand out. --}}
        <div class="border-gradient rounded-[20px] p-[22.5px] shadow-[0_20px_50px_color-mix(in_srgb,var(--color-brand)_25%,transparent),0_4px_12px_rgba(0,0,0,0.08)]"
             style="{{ $width === 'wide' ? 'width: 1520px' : 'width: '.(int) $width.'px' }}; max-width: calc(100% - 32px)">
            <div class="flex items-center justify-between">
                <h2 class="text-[16px] font-bold leading-[24px]">{{ $title }}</h2>
                <button type="button" @click="open = false" class="hover-tint flex size-[28px] items-center justify-center rounded-full text-[20px] leading-none text-muted" aria-label="Close">&times;</button>
            </div>
            <div class="mt-[24px]">{{ $slot }}</div>
        </div>
    </div>
</div>
