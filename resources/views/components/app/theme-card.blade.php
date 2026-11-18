@php
    $user = auth()->user();
    $themes = config('agapay.themes');
    $palette = $user?->color_theme ?? config('agapay.default_theme');
@endphp
{{-- Appearance (styled like Return to Login): the dark mode switch, and below it the colour theme's swatches. Both are
     saved on the account (AppearanceController) and in this browser, and flip the page's colours at once (app.css:
     html.dark, html[data-palette]). The page cross-fades into the new look (a view transition, skipped for reduced
     motion); the switch has its own transition name so it stays out of the fade and its knob and icons still animate.
     One card for both keeps the side panel's bottom group short, clear of the menu above. --}}
<div x-data="{
        night: document.documentElement.classList.contains('dark'),
        palette: document.documentElement.dataset.palette || @js($palette),
        labels: @js(collect($themes)->map(fn ($t) => $t['label'])),
        save(change) {
            fetch(@js(route('account.appearance')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(change) });
        },
        apply(change) {
            const root = document.documentElement;
            const flip = async () => {
                if ('dark' in change) { this.night = change.dark; root.classList.toggle('dark', change.dark); root.dataset.dark = change.dark ? '1' : '0'; }
                if ('palette' in change) { this.palette = change.palette; root.dataset.palette = change.palette; }
                try {
                    localStorage.setItem('agapay-theme', this.night ? 'dark' : 'light');
                    localStorage.setItem('agapay-palette', this.palette);
                } catch (e) {}
                await this.$nextTick();
            };
            if (document.startViewTransition && ! matchMedia('(prefers-reduced-motion: reduce)').matches) { document.startViewTransition(flip); } else { flip(); }
            this.save(change);
        },
     }"
     {{ $attributes->class('bg-brand-card relative h-[94px] w-[211px] rounded-[15px] pr-[12px] pl-[14px]') }}>
    <div class="flex h-[52px] items-center justify-between">
        <div class="text-white">
            <p class="text-[15px] font-bold leading-[18px]">DARK MODE</p>
            <p class="text-[12px] leading-[16px]" x-text="night ? 'On' : 'Off'">Off</p>
        </div>
        <button type="button" role="switch" :aria-checked="night.toString()" aria-checked="false" aria-label="Dark mode"
                @click="apply({ 'dark': ! night })"
                style="view-transition-name: theme-switch"
                class="relative h-[28px] w-[54px] shrink-0 rounded-full transition-colors duration-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                :class="night ? 'bg-[var(--switch-night)]' : 'bg-white/35'">
            {{-- Stars fade in on the night track. --}}
            <span class="absolute top-[7px] left-[10px] size-[3px] rounded-full bg-white transition-opacity duration-300" :class="night ? 'opacity-100' : 'opacity-0'"></span>
            <span class="absolute top-[15px] left-[16px] size-[2px] rounded-full bg-white transition-opacity duration-300" :class="night ? 'opacity-100' : 'opacity-0'"></span>
            <span class="absolute top-[3px] left-[3px] flex size-[22px] items-center justify-center rounded-full bg-white shadow-[0_2px_6px_rgba(0,0,0,0.25)] transition-transform duration-300 ease-[cubic-bezier(.4,1.4,.6,1)]"
                  :class="night ? 'translate-x-[26px]' : 'translate-x-0'">
                {{-- Both icons stay in the knob; app.css spins the sun away and turns the moon in while html.dark is on. --}}
                <svg class="theme-sun absolute size-[14px] text-[#f5a524]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4" fill="currentColor" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
                </svg>
                <svg class="theme-moon absolute size-[14px] text-brand" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z" />
                </svg>
            </span>
        </button>
    </div>
    {{-- Colour theme: one swatch per colourway (its two main colours); the chosen one is ringed and named. --}}
    <div class="flex h-[34px] items-center justify-between border-t border-white/30">
        <p class="truncate text-[12px] font-bold leading-[16px] text-white" x-text="labels[palette]">{{ $themes[$palette]['label'] ?? '' }}</p>
        <div class="flex shrink-0 gap-[7px]" role="radiogroup" aria-label="Colour theme">
            @foreach ($themes as $key => $theme)
                <button type="button" role="radio" data-palette-swatch="{{ $key }}" aria-label="{{ $theme['label'] }}" title="{{ $theme['label'] }}"
                        aria-checked="{{ $palette === $key ? 'true' : 'false' }}" :aria-checked="(palette === @js($key)).toString()"
                        @click="apply({ palette: @js($key) })"
                        class="theme-swatch size-[22px] shrink-0 rounded-full border-2 border-white/80"
                        style="background: linear-gradient(135deg, {{ $theme['swatch'][0] }} 0 55%, {{ $theme['swatch'][1] }} 55% 100%)"></button>
            @endforeach
        </div>
    </div>
</div>
