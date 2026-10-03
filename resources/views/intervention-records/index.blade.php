@php($cols = 'grid-cols-[251px_260px_392px_104px_134px_187px_98px]')
<x-layouts.app title="INTERVENTION RECORDS">
    {{-- Figma 430:1603 / 440:166 --}}
    <x-intervention.flash />

    <x-ui.card title="Intervention Records">
        <x-slot:actions>
            <a href="{{ route('intervention-records.create') }}"
               class="-mt-[11.5px] -mr-[18.5px] flex h-[39px] w-[230px] items-center justify-center rounded-[8px] bg-brand-soft text-[14px] font-bold text-white transition-colors hover:bg-[#4b32c3]">+ Add Record</a>
        </x-slot:actions>

        <form method="GET" class="relative mt-[20.5px] flex h-[39px] items-center gap-[25px] pl-[1.5px]">
            <x-ui.pill-input name="name" placeholder="Search Name..." :value="request()->queryText('name')" width="230" />
            <x-ui.pill-input name="rsbsa" placeholder="RSBSA Number..." :value="request()->queryText('rsbsa')" width="230" />
            <x-ui.pill-select name="intervention" label="Intervention" :options="['' => 'All'] + $interventionOptions" :selected="request()->queryText('intervention')" width="377" />
            <span class="absolute left-[919.5px] text-[14px] font-medium text-muted">Qty / Unit</span>
            <span class="absolute left-[1042.5px] text-[12px] font-medium text-muted">Date Distributed</span>
            <span class="absolute left-[1157.5px] w-[155px] text-center text-[14px] font-medium text-muted">Action</span>
            <span class="absolute left-[1333px] w-[137px] text-center text-[12px] font-medium text-muted">Distribution Status</span>
            <button type="submit" class="sr-only">Apply filters</button>
        </form>

        <div class="divider mt-[15px]"></div>

        <div class="mt-[3px]">
            @forelse ($records as $record)
                <div class="grid h-[45px] {{ $cols }} items-center pl-[16.5px] text-[14px] font-bold leading-[21px]">
                    <a href="{{ route('beneficiaries.show', $record->beneficiary) }}" class="truncate pr-[12px] hover:text-brand hover:underline">{{ $record->beneficiary->fullName() }}</a>
                    <span>{{ $record->beneficiary->rsbsaDisplay() }}</span>
                    <span class="truncate pr-[12px]">{{ $record->deLabel() }}</span>
                    <span>{{ $record->quantityDisplay() }}</span>
                    <span>{{ $record->date_distributed?->format('M j, Y') ?? '-' }}</span>
                    <x-intervention.chip-select name="claim_status" :options="['unclaimed' => 'Unclaimed', 'claimed' => 'Claimed']" :selected="$record->claim_status"
                                                :tone="$record->claimTone()"
                                                :actions="['claimed' => route('intervention-records.claim', $record), 'unclaimed' => route('intervention-records.unclaim', $record)]" />
                    <a href="{{ route('intervention-records.edit', $record) }}"
                       class="flex h-[39px] w-[98px] items-center justify-center rounded-[10px] border-4 border-[#7e80ff] bg-white text-[11px] transition-colors hover:bg-[#efeaff] hover:text-brand">Edit</a>
                </div>
            @empty
                <p class="py-[14px] pl-[16.5px] text-[14px] font-bold">No intervention records match these filters.</p>
            @endforelse
        </div>

        @if ($records->hasPages())
            <div class="mt-[12px] text-[14px]">{{ $records->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
