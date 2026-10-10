{{-- Dark mode switch (styled like Return to Login): remembers the choice and flips the page's colours (app.css, html.dark). --}}
<div x-data="{ night: (() => { try { return localStorage.getItem('agapay-theme') === 'dark'; } catch (e) { return false; } })() }"
     {{ $attributes->class('bg-brand-card relative flex h-[60px] w-[211px] items-center justify-between rounded-[15px] pr-[12px] pl-[14px]') }}>
    <div class="text-white">
        <p class="text-[15px] font-bold leading-[18px]">DARK MODE</p>
        <p class="text-[12px] leading-[16px]" x-text="night ? 'On' : 'Off'">Off</p>
    </div>
    <button type="button" role="switch" :aria-checked="night.toString()" aria-checked="false" aria-label="Dark mode"
            @click="night = ! night; document.documentElement.classList.toggle('dark', night); try { localStorage.setItem('agapay-theme', night ? 'dark' : 'light'); } catch (e) {}"
            class="relative h-[28px] w-[54px] shrink-0 rounded-full transition-colors duration-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
            :class="night ? 'bg-[#2b2d6e]' : 'bg-white/35'">
        {{-- Stars fade in on the night track. --}}
        <span class="absolute top-[7px] left-[10px] size-[3px] rounded-full bg-white transition-opacity duration-300" :class="night ? 'opacity-100' : 'opacity-0'"></span>
        <span class="absolute top-[15px] left-[16px] size-[2px] rounded-full bg-white transition-opacity duration-300" :class="night ? 'opacity-100' : 'opacity-0'"></span>
        <span class="absolute top-[3px] left-[3px] flex size-[22px] items-center justify-center rounded-full bg-white shadow-[0_2px_6px_rgba(0,0,0,0.25)] transition-transform duration-300 ease-[cubic-bezier(.4,1.4,.6,1)]"
              :class="night ? 'translate-x-[26px]' : 'translate-x-0'">
            {{-- Both icons stay in the knob; app.css spins the sun away and turns the moon in while html.dark is on. --}}
            <svg class="theme-sun absolute size-[14px] text-[#f5a524]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                <circle cx="12" cy="12" r="4" fill="currentColor" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
            </svg>
            <svg class="theme-moon absolute size-[14px] text-[#5a5de3]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z" />
            </svg>
        </span>
    </button>
</div>
