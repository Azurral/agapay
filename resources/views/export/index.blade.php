@php
    $cols = 'grid-cols-[190px_120px_200px_190px_80px_100px_120px_100px_1fr]';
    $headings = ['Name', 'RSBSA No.', 'Address', 'Program', 'Cycle', 'Claim Status', 'Amount', 'Crises'];
@endphp
<x-layouts.app title="EXPORT BENEFICIARY LIST">
    {{-- Figma 329:3134, widened to one row per program given. --}}
    <x-ui.card title="Export Beneficiary List">
        <p class="mt-[4px] pl-[3px] text-[14px] font-medium leading-[18px] text-muted">
            Includes names, RSBSA No., address, programs, claim status, amounts and crises. Birthdates and contact numbers are left out.
        </p>

        <div class="mt-[23px] grid {{ $cols }} pl-[15px] text-[14px] font-medium leading-[13px] text-brand-soft" aria-hidden="true">
            @foreach ($headings as $heading)
                <span>{{ $heading }}</span>
            @endforeach
        </div>
        <div class="divider mt-[17px] ml-[1px]"></div>

        <div role="list" class="mt-[4px]">
            @forelse ($beneficiaries as $beneficiary)
                @foreach ($export->map($beneficiary) as [$name, $rsbsa, $address, $program, $source, $cycle, $claim, $amount, $crises])
                    <div role="listitem" class="grid min-h-[45.5px] {{ $cols }} items-center pl-[15px] text-[14px] font-bold leading-[18px]">
                        <span class="truncate pr-[10px]">{{ $name }}</span>
                        <span class="truncate pr-[10px]">{{ $rsbsa }}</span>
                        <span class="truncate pr-[10px]" title="{{ $address }}">{{ $address }}</span>
                        <span class="truncate pr-[10px]">{{ $program !== '' ? "{$source} - {$program}" : '—' }}</span>
                        <span>{{ $cycle }}</span>
                        <span>{{ $claim }}</span>
                        <span class="truncate pr-[10px]">{{ $amount }}</span>
                        <span class="truncate" title="{{ $crises }}">{{ $crises }}</span>
                    </div>
                @endforeach
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
           class="border-gradient pill-button flex h-[39px] w-[263px] items-center justify-center gap-[2px] rounded-[50px] text-[20px] font-bold leading-[24px]">
            <img src="{{ asset('images/figma/icons/plus.svg') }}" alt="" class="size-[23px]"> Export as .xlsx
        </a>
    </div>
</x-layouts.app>
