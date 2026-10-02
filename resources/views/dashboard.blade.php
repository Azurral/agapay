<x-layouts.app title="HOME DASHBOARD">
    @if ($showsEncodingQueue)
        {{-- Figma 237:1659 --}}
        <x-ui.card title="Pending Encoding Queue">
            <div class="mt-[18px] grid grid-cols-[287px_250px_380px_1fr_155px] text-[14px] font-medium leading-[20px] text-muted" aria-hidden="true">
                <span>Name</span><span>Source</span><span>Issue</span><span>Date Added</span><span class="text-center">Status</span>
            </div>
            <div class="divider mt-[13px]"></div>
            <div role="list">
                @forelse ($queue as $record)
                    <a role="listitem" @if (Route::has('beneficiaries.show')) href="{{ route('beneficiaries.show', ['beneficiary' => $record, 'edit' => 1]) }}" @endif
                       class="hover-tint -mx-[8px] grid min-h-[51px] grid-cols-[287px_250px_380px_1fr_155px] items-center rounded-[10px] px-[8px] text-[14px] font-bold leading-[21px]">
                        <span class="truncate pr-[12px]">{{ $record->fullName() }}</span>
                        <span>{{ $record->source === \App\Models\Beneficiary::SOURCE_IMPORT ? 'Excel Import' : 'Manual Entry' }}</span>
                        <span class="truncate pr-[12px]">{{ $record->encoding_issue }}</span>
                        <span>{{ $record->created_at->format('M j, Y') }}</span>
                        <x-ui.status-chip tone="bad">Incomplete</x-ui.status-chip>
                    </a>
                @empty
                    <p class="py-[14px] text-[14px] font-bold">No records are waiting to be encoded.</p>
                @endforelse
            </div>
        </x-ui.card>
    @else
        <x-beneficiary.table title="All Beneficiaries" :beneficiaries="$recent" />
    @endif
</x-layouts.app>
