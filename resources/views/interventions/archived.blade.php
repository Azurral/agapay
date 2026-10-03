@php($label = strtoupper($source))
@php($cols = 'grid-cols-[220px_172px_320px_220px_338px_155px]')
<x-layouts.app :title="$label.' INTERVENTION LIST'">
    {{-- Figma 344:236 (DA) / 344:531 (LGU) --}}
    <x-intervention.flash />

    <x-ui.card :title="$label.' Intervention - Recently Deleted'">
        <x-slot:actions>
            <div class="-mt-[11.5px] -mr-[18.5px]"><x-intervention.segmented-toggle :source="$source" active="archived" /></div>
        </x-slot:actions>

        <form method="GET" class="relative mt-[20.5px] flex h-[39px] items-center pl-[1.5px]">
            <x-ui.pill-input name="q" placeholder="Search name..." :value="request()->queryText('q')" width="220" />
            @foreach ([236.5 => 'RSBSA No.', 408.5 => 'Reason Deleted', 728.5 => 'Deleted On', 948.5 => 'Deleted By'] as $left => $heading)
                <span class="absolute text-[14px] font-medium text-muted" style="left: {{ $left }}px">{{ $heading }}</span>
            @endforeach
            <button type="submit" class="sr-only">Search</button>
        </form>

        <div class="divider mt-[15px]"></div>

        <div class="mt-[3px]">
            @forelse ($records as $record)
                <div class="grid min-h-[45px] {{ $cols }} items-center py-[3px] pl-[16.5px] text-[14px] font-bold leading-[16px]">
                    <span class="truncate pr-[12px]">{{ $record->beneficiary->fullName() }}</span>
                    <span>{{ $record->beneficiary->rsbsaDisplay() }}</span>
                    <span class="pr-[20px]" title="{{ $record->intervention->name }} ({{ $record->cycle->code }})">{{ $record->delete_reason }}</span>
                    <span>{{ $record->deleted_at->format('M j, Y') }}</span>
                    <span>{{ $record->deleter?->username ?? 'System' }}</span>
                    <form method="POST" action="{{ route('intervention-records.restore', $record) }}">
                        @csrf
                        <button type="submit" class="h-[31px] w-[155px] rounded-[10px] border-4 border-ok bg-white text-[14px] font-bold transition-colors hover:bg-ok/40">Restore</button>
                    </form>
                </div>
            @empty
                <p class="py-[14px] pl-[16.5px] text-[14px] font-bold">No archived records.</p>
            @endforelse
        </div>

        @if ($records->hasPages())
            <div class="mt-[12px] text-[14px]">{{ $records->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
