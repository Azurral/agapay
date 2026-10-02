<x-layouts.app title="BENEFICIARY PROFILES">
    {{-- Figma 430:1276 / 440:43 --}}
    <x-ui.card title="Beneficiary Profiles">
        <form method="GET" class="mt-[18.5px] flex items-center gap-[25px]">
            <x-ui.pill-input name="name" placeholder="Search Name..." :value="request()->queryText('name')" width="230" />
            <x-ui.pill-input name="rsbsa" placeholder="RSBSA Number..." :value="request()->queryText('rsbsa')" width="230" />
            <x-ui.pill-select name="barangay" label="Barangay" :options="['' => 'All'] + $barangays->all()" :selected="request()->queryText('barangay')" width="230" />
            {{-- Interventions arrive in Phase 4. --}}
            <x-ui.pill-select name="intervention" label="Intervention" :options="['' => 'All']" width="230" />
            <button type="submit" class="sr-only">Apply filters</button>
        </form>

        <div class="mt-[18px] grid grid-cols-[251px_260px_255px_262px_1fr_155px_111px] pl-[15px] text-[14px] font-medium leading-[20px] text-muted" aria-hidden="true">
            <span>Name</span><span>RSBSA Number</span><span>Barangay</span><span>Intervention</span><span>Household</span>
            <span class="text-center">Status</span><span class="text-right pr-[6.5px]">Action</span>
        </div>
        <div class="divider mt-[13px]"></div>

        @forelse ($beneficiaries as $beneficiary)
            @php($members = $beneficiary->household?->members_count ?? 1)
            <div class="grid min-h-[51px] grid-cols-[251px_260px_255px_262px_1fr_155px_111px] items-center pl-[15px] text-[14px] font-bold leading-[21px]">
                <span class="truncate pr-[12px]">{{ $beneficiary->fullName() }}</span>
                <span>{{ $beneficiary->rsbsaDisplay() }}</span>
                <span>{{ $beneficiary->barangay?->name }}</span>
                <span>—</span>
                <span>{{ $members }} {{ Str::plural('member', $members) }}</span>
                <span class="text-center">—</span>
                <span class="flex justify-end">
                    <a @if (Route::has('beneficiaries.show')) href="{{ route('beneficiaries.show', ['beneficiary' => $beneficiary, 'edit' => 1]) }}" @endif
                       class="hover-tint flex h-[39px] w-[98px] items-center justify-center rounded-[10px] border-4 border-[#7e80ff] bg-white">Edit</a>
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
