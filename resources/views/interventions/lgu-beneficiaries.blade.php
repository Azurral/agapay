@php($cols = 'grid-cols-[260px_190px_1fr_130px_200px_150px]')
<x-layouts.app title="LGU INTERVENTION LIST">
    <x-intervention.flash />

    {{-- Every farmer in Bontoc, with or without an RSBSA number: LGU programs serve them all. --}}
    <x-ui.card title="LGU Beneficiaries">
        <x-slot:actions>
            <div class="-mt-[11.5px] -mr-[18.5px]"><x-intervention.lgu-tabs active="beneficiaries" /></div>
        </x-slot:actions>

        <form method="GET" class="mt-[20.5px] flex h-[39px] items-center gap-[25px] pl-[1.5px]">
            <x-ui.pill-input name="name" placeholder="Search Name..." :value="request()->queryText('name')" :width="260" />
            <x-ui.pill-select name="barangay" label="Barangay" :options="['' => 'All'] + $barangays->all()" :selected="request()->queryText('barangay')" :width="260" />
            <button type="submit" class="sr-only">Apply filters</button>
        </form>

        <div class="divider mt-[15px]"></div>

        <div class="mt-[12px] grid {{ $cols }} pl-[16.5px] text-[14px] font-medium leading-[18px] text-muted">
            <span>Name</span><span>RSBSA</span><span>Address</span><span>Farm Area</span><span>Barangay</span><span class="text-center">Crisis Reports</span>
        </div>

        <div class="mt-[3px]">
            @forelse ($beneficiaries as $beneficiary)
                <div class="grid h-[45px] {{ $cols }} items-center pl-[16.5px] text-[14px] font-bold leading-[21px]">
                    <a href="{{ route('beneficiaries.show', $beneficiary) }}" class="truncate pr-[12px] hover:text-brand hover:underline">{{ $beneficiary->fullName() }}</a>
                    <span class="truncate pr-[12px]">{{ $beneficiary->rsbsaDisplay() }}</span>
                    <span class="truncate pr-[12px]" title="{{ $beneficiary->fullAddress() }}">{{ $beneficiary->address }}</span>
                    <span class="truncate pr-[12px]">{{ $beneficiary->farmAreaDisplay() ?? '—' }}</span>
                    <span class="truncate pr-[12px]">{{ $beneficiary->barangay?->name }}</span>
                    <span class="text-center">{{ $beneficiary->damage_reports_count }}</span>
                </div>
            @empty
                <p class="py-[14px] pl-[16.5px] text-[13px] font-bold">No farmers match these filters.</p>
            @endforelse
        </div>

        <div class="mt-[12px] text-[14px]">{{ $beneficiaries->links() }}</div>
    </x-ui.card>
</x-layouts.app>
