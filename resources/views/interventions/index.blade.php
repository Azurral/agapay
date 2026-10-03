<x-layouts.app title="INTERVENTION TYPES">
    {{-- Figma 329:2475 / 407:2 --}}
    <x-ui.card title="Select Intervention Type" />

    <div class="mt-[3px] grid grid-cols-2 gap-[23px]">
        @foreach ([
            ['Department of Agriculture [DA]', 'National', 'View DA Beneficiary List', route('interventions.da')],
            ['Local Government Unit [LGU]', 'Municipal', 'View LGU Beneficiary List', route('interventions.lgu')],
        ] as [$title, $scope, $button, $url])
            <section class="bg-brand-card relative h-[246px] rounded-[15px] p-[13px] text-white">
                <h2 class="text-[16px] font-bold leading-[20px]">{{ $title }}</h2>
                <p class="text-[12px] leading-[16px]">{{ $scope }}</p>
                <a href="{{ $url }}"
                   class="absolute right-[13px] bottom-[13px] left-[13px] flex h-[31px] items-center justify-center rounded-[50px] bg-white text-[16px] font-bold text-ink transition-colors hover:bg-[#4b32c3] hover:text-white">{{ $button }}</a>
            </section>
        @endforeach
    </div>
</x-layouts.app>
