@props(['title'])
<header class="relative z-20 flex h-[70px] items-center justify-between border border-white bg-white/10 pr-[15px] shadow-[0_4px_4px_0_rgba(0,0,0,0.05)]">
    <div class="flex items-center">
        <button type="button" @click="nav = ! nav" :aria-expanded="nav.toString()" aria-controls="side-panel"
                aria-label="Open menu" class="hover-tint ml-[14px] flex size-[42px] items-center justify-center rounded-[12px]">
            <svg class="size-[24px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <img src="{{ asset('images/logo.svg') }}" alt="Agapay logo" class="ml-[10px] block size-[38px]">
        <span class="ml-[15px] text-[19px] font-bold leading-[28px]">Agapay</span>
    </div>
    <div class="text-right text-[16px] font-bold leading-[20px]">
        <p>{{ (config('agapay.frozen_now') ? \Illuminate\Support\Carbon::parse(config('agapay.frozen_now')) : now())->format('D, F j') }}</p>
        <p>{{ $title }}</p>
    </div>
</header>
