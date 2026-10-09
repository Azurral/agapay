@props(['title'])
{{-- Pinned to the top only while the side panel is open, so both stay together when the page scrolls. --}}
<header class="relative z-20 flex h-[70px] items-center justify-between border border-white bg-white/10 pr-[15px] shadow-[0_4px_4px_0_rgba(0,0,0,0.05)] transition-transform duration-200"
        :class="{ 'sticky top-0 bg-white': nav || hiding, '-translate-y-full': hiding, 'header-drop': nav && window.scrollY > 0 }"
        x-bind:style="(nav || hiding) && 'position: sticky'">
    {{-- The logo is the menu button: it opens the side panel (▾ turns while open). --}}
    <button type="button" @click="nav = ! nav" :aria-expanded="nav.toString()" aria-controls="side-panel"
            aria-label="Open menu" :aria-label="nav ? 'Close menu' : 'Open menu'"
            class="group ml-[10px] flex h-[52px] items-center gap-[10px] rounded-[16px] pr-[12px] pl-[10px] transition-colors hover:bg-[#efeaff] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-soft"
            :class="nav && 'bg-[#efeaff]'">
        <img src="{{ asset('images/logo.svg') }}" alt="Agapay logo" class="block size-[38px] transition-transform duration-200 group-hover:scale-105">
        <span class="text-[19px] font-bold leading-[28px] group-hover:text-brand" :class="nav && 'text-brand'">Agapay</span>
        <svg class="size-[18px] text-muted transition-transform duration-300 group-hover:text-brand" :class="nav && 'rotate-180 text-brand'"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m6 9 6 6 6-6" />
        </svg>
    </button>
    <div class="text-right text-[16px] font-bold leading-[20px]">
        <p>{{ (config('agapay.frozen_now') ? \Illuminate\Support\Carbon::parse(config('agapay.frozen_now')) : now())->format('D, F j') }}</p>
        <p>{{ $title }}</p>
    </div>
</header>
