@props(['beneficiaries', 'title', 'empty' => 'No beneficiaries recorded yet.'])
{{-- Figma 310:2 "All Beneficiaries" table. Intervention and Status columns are filled by Phase 4. --}}
<x-ui.card :title="$title">
    <div class="mt-[18px] grid grid-cols-[310px_261px_236px_234px_1fr_155px] text-[14px] font-medium leading-[20px] text-muted" aria-hidden="true">
        <span>Name</span><span>RSBSA Number</span><span>Barangay</span><span>Household</span><span>Intervention</span><span class="text-center">Status</span>
    </div>
    <div class="divider mt-[13px]"></div>

    <div role="list">
        @forelse ($beneficiaries as $beneficiary)
            @php($members = $beneficiary->household?->members_count ?? 1)
            <a role="listitem" @if (Route::has('beneficiaries.show')) href="{{ route('beneficiaries.show', $beneficiary) }}" @endif
               class="hover-tint -mx-[8px] grid h-[45px] grid-cols-[310px_261px_236px_234px_1fr_155px] items-center rounded-[10px] px-[8px] text-[14px] font-bold leading-[21px]">
                <span class="truncate pr-[12px]">{{ $beneficiary->fullName() }}</span>
                <span>{{ $beneficiary->rsbsaDisplay() }}</span>
                <span>{{ $beneficiary->barangay?->name }}</span>
                <span>{{ $members }} {{ Str::plural('member', $members) }}</span>
                <span class="truncate pr-[12px]">{{ $beneficiary->latestRecord?->intervention->name ?? '—' }}</span>
                @if ($beneficiary->latestRecord)
                    <x-ui.status-chip :tone="$beneficiary->latestRecord->claimTone()">{{ $beneficiary->latestRecord->claimLabel() }}</x-ui.status-chip>
                @else
                    <span class="text-center">—</span>
                @endif
            </a>
        @empty
            <p class="py-[14px] text-[14px] font-bold">{{ $empty }}</p>
        @endforelse
    </div>

    @if ($beneficiaries instanceof \Illuminate\Contracts\Pagination\Paginator && $beneficiaries->hasPages())
        <div class="mt-[12px] text-[14px]">{{ $beneficiaries->links() }}</div>
    @endif
</x-ui.card>
