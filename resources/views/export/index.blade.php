@php
    $cols = 'grid-cols-[290px_254px_1fr]';
@endphp
<x-layouts.app title="EXPORT BENEFICIARY LIST">
    {{-- Figma 329:3134 --}}
    <x-ui.card title="Export Beneficiary List to OMAG / DA">
        <p class="mt-[4px] pl-[3px] text-[14px] font-medium leading-[13px] text-muted">Only Name, RSBSA No., and Address are included in this export.</p>

        <div class="mt-[23px] grid {{ $cols }} pl-[15px] text-[14px] font-medium leading-[13px] text-brand-soft" aria-hidden="true">
            <span>Name</span><span>RSBSA Number</span><span>Address</span>
        </div>
        <div class="divider mt-[17px] ml-[1px]"></div>

        <div role="list" class="mt-[4px]">
            @forelse ($beneficiaries as $beneficiary)
                @php [$name, $rsbsa, $address] = $export->map($beneficiary); @endphp
                <div role="listitem" class="grid h-[45.5px] {{ $cols }} items-center pl-[15px] text-[14px] font-bold leading-[13px]">
                    <span class="truncate pr-[12px]">{{ $name }}</span>
                    <span>{{ $rsbsa }}</span>
                    <span class="truncate">{{ $address }}</span>
                </div>
            @empty
                <p class="py-[14px] pl-[15px] text-[14px] font-bold">No beneficiaries to export yet.</p>
            @endforelse
        </div>

        @if ($beneficiaries->hasPages())
            <div class="mt-[12px]">{{ $beneficiaries->links() }}</div>
        @endif
    </x-ui.card>

    <div class="-mt-[0.5px]">
        <a href="{{ route('export.download') }}"
           class="pill-button flex h-[39px] w-[263px] items-center justify-center gap-[2px] rounded-[50px] border-[1.5px] border-brand-soft bg-white text-[20px] font-bold leading-[24px]">
            <img src="{{ asset('images/figma/icons/plus.svg') }}" alt="" class="size-[23px]"> Export as .xlsx
        </a>
    </div>
</x-layouts.app>
