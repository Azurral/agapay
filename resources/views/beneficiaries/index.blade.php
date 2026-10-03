<x-layouts.app title="BENEFICIARY PROFILES">
    {{-- Figma 430:1276 / 440:43 --}}
    <x-ui.card title="Beneficiary Profiles">
        {{-- Figma: the four filters double as the Name/RSBSA/Barangay/Intervention column heads. --}}
        <form method="GET" class="mt-[18.5px] grid grid-cols-[255px_255px_255px_255px_1fr_155px_126px] items-center text-[14px] font-medium leading-[20px]">
            <x-ui.pill-input name="name" placeholder="Search Name..." :value="request()->queryText('name')" width="230" />
            <x-ui.pill-input name="rsbsa" placeholder="RSBSA Number..." :value="request()->queryText('rsbsa')" width="230" />
            <x-ui.pill-select name="barangay" label="Barangay" :options="['' => 'All'] + $barangays->all()" :selected="request()->queryText('barangay')" width="230" />
            <x-ui.pill-select name="intervention" label="Intervention" :options="['' => 'All'] + $interventionOptions" :selected="request()->queryText('intervention')" width="230" />
            <span class="pl-[23px] text-muted">Household</span>
            <span class="text-center text-muted">Status</span>
            <span class="flex justify-end"><span class="w-[98px] text-center text-muted">Action</span></span>
            <button type="submit" class="sr-only">Apply filters</button>
        </form>
        <div class="divider mt-[14px] mb-[4px]"></div>

        @forelse ($beneficiaries as $beneficiary)
            @php($members = $beneficiary->household?->members_count ?? 1)
            <div class="grid h-[45px] grid-cols-[251px_260px_255px_262px_1fr_155px_126px] items-center pl-[15px] text-[14px] font-bold leading-[21px]">
                <span class="truncate pr-[12px]">{{ $beneficiary->fullName() }}</span>
                <span>{{ $beneficiary->rsbsaDisplay() }}</span>
                <span>{{ $beneficiary->barangay?->name }}</span>
                <span class="truncate pr-[12px]">{{ $beneficiary->latestRecord?->intervention->name ?? '—' }}</span>
                <span>{{ $members }} {{ Str::plural('member', $members) }}</span>
                @if ($beneficiary->latestRecord)
                    <span class="flex justify-center"><x-ui.status-chip :tone="$beneficiary->latestRecord->claimTone()">{{ $beneficiary->latestRecord->claimLabel() }}</x-ui.status-chip></span>
                @else
                    <span class="text-center">—</span>
                @endif
                <span class="flex justify-end">
                    <a @if (Route::has('beneficiaries.show')) href="{{ route('beneficiaries.show', ['beneficiary' => $beneficiary, 'edit' => 1]) }}" @endif
                       class="hover-tint flex h-[39px] w-[98px] items-center justify-center rounded-[10px] border-4 border-[#7e80ff] bg-white hover:bg-[#efeaff] hover:text-brand">Edit</a>
                </span>
            </div>
        @empty
            <p class="py-[14px] pl-[15px] text-[14px] font-bold">No beneficiaries match these filters.</p>
        @endforelse

        @if ($beneficiaries->hasPages())
            <div class="mt-[12px] text-[14px]">{{ $beneficiaries->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
