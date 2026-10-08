@props(['title'])
<header class="relative z-20 flex h-[70px] items-center justify-between border border-white bg-white/10 pr-[15px] shadow-[0_4px_4px_0_rgba(0,0,0,0.05)]">
    <div class="flex items-center">
        <button type="button" @click="nav = ! nav" :aria-expanded="nav.toString()" aria-controls="side-panel"
                aria-label="Open menu" :aria-label="nav ? 'Close menu' : 'Open menu'"
                class="group ml-[14px] flex size-[42px] items-center justify-center rounded-[14px] bg-[linear-gradient(135deg,#7e80ff_0%,#5a5de3_55%,#4b4fc4_100%)] shadow-[0_4px_12px_rgba(90,93,227,0.35)] transition duration-200 hover:-translate-y-[1px] hover:shadow-[0_6px_16px_rgba(90,93,227,0.5)] active:translate-y-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-soft">
            {{-- Three bars of different lengths that fold into an X while the panel is open. --}}
            <span class="relative block h-[16px] w-[20px]" aria-hidden="true">
                <span class="absolute left-0 top-0 h-[2.5px] w-[20px] rounded-full bg-white transition-all duration-300"
                      :class="nav ? 'translate-y-[7px] rotate-45' : 'group-hover:w-[14px]'"></span>
                <span class="absolute left-0 top-[7px] h-[2.5px] w-[14px] rounded-full bg-cyan transition-all duration-300"
                      :class="nav ? 'opacity-0 -translate-x-[6px]' : 'group-hover:w-[20px]'"></span>
                <span class="absolute left-0 top-[14px] h-[2.5px] w-[20px] rounded-full bg-white transition-all duration-300"
                      :class="nav ? '-translate-y-[7px] -rotate-45' : 'group-hover:w-[10px]'"></span>
            </span>
        </button>
        <img src="{{ asset('images/logo.svg') }}" alt="Agapay logo" class="ml-[10px] block size-[38px]">
        <span class="ml-[15px] text-[19px] font-bold leading-[28px]">Agapay</span>
    </div>
    <div class="text-right text-[16px] font-bold leading-[20px]">
        <p>{{ (config('agapay.frozen_now') ? \Illuminate\Support\Carbon::parse(config('agapay.frozen_now')) : now())->format('D, F j') }}</p>
        <p>{{ $title }}</p>
    </div>
</header>
