@php
    $statuses = ['' => 'All'] + \App\Models\AssistanceRequest::STATUSES;
    $cards = ['requests' => 'Total Requests', 'pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'released' => 'Released'];
    $groups = ['barangays' => 'By Barangay', 'sitios' => 'By Sitio/Purok', 'crises' => 'By Crisis', 'crops' => 'By Crop'];
    $cols = 'grid-cols-[1.6fr_90px_90px_90px_90px_90px_120px_1.4fr]';
    $listCols = 'grid-cols-[110px_1.3fr_120px_1.3fr_70px_1.2fr_160px_1.6fr]';
    $query = array_filter(['tab' => 'requests', 'disaster' => $filters['disaster'], 'status' => $filters['status'], 'intervention' => $filters['intervention']]);
@endphp
<x-layouts.app title="LGU INTERVENTION LIST">
    <x-intervention.flash />
    @error('request')
        <p class="rounded-[10px] border-[1.5px] border-bad bg-white px-[16px] py-[10px] text-[14px] font-bold text-danger" role="alert">{{ $message }}</p>
    @enderror
    @error('decision_note')
        <p class="rounded-[10px] border-[1.5px] border-bad bg-white px-[16px] py-[10px] text-[14px] font-bold text-danger" role="alert">{{ $message }}</p>
    @enderror

    <x-ui.card title="Assistance Requests">
        <x-slot:actions>
            <div class="-mt-[11.5px] -mr-[18.5px]"><x-intervention.lgu-tabs active="requests" /></div>
        </x-slot:actions>

        {{-- Filters apply to the summary, the breakdowns, the list and the Excel download. --}}
        <form method="GET" class="mt-[20.5px] flex h-[39px] items-center gap-[20px] pl-[1.5px]">
            <input type="hidden" name="tab" value="requests">
            <x-ui.pill-select name="disaster" label="Crisis" :options="['' => 'All'] + $disasters->all()" :selected="(string) $filters['disaster']" :width="300" />
            <x-ui.pill-select name="status" label="Status" :options="$statuses" :selected="(string) $filters['status']" :width="220" />
            <x-ui.pill-select name="intervention" label="Program" :options="['' => 'All'] + $interventions->all()" :selected="(string) $filters['intervention']" :width="340" />
            <button type="submit" class="sr-only">Apply filters</button>
            <div class="ml-auto flex gap-[12px]">
                @can('requests.create')
                    <a href="{{ route('assistance-requests.create') }}" class="border-gradient pill-button flex h-[39px] items-center rounded-[50px] px-[20px] text-[15px] font-bold">+ New Request</a>
                @endcan
                <a href="{{ route('assistance-requests.export', $query) }}" class="border-gradient pill-button flex h-[39px] items-center rounded-[50px] px-[20px] text-[15px] font-bold">Export as .xlsx</a>
            </div>
        </form>

        <div class="mt-[18px] grid grid-cols-5 gap-[12px]">
            @foreach ($cards as $key => $label)
                <div class="rounded-[15px] border-[1.5px] border-black/10 bg-white px-[16px] py-[12px]">
                    <p class="text-[13px] font-medium text-muted">{{ $label }}</p>
                    <p class="text-[26px] font-bold leading-[32px]">{{ $stats['totals'][$key] }}</p>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    @foreach ($groups as $key => $title)
        <x-ui.card :title="$title">
            <div class="mt-[12px] grid {{ $cols }} pl-[4px] text-[13px] font-medium text-muted" aria-hidden="true">
                <span>{{ \Illuminate\Support\Str::after($title, 'By ') }}</span><span>Requests</span><span>Farmers</span><span>Pending</span><span>Approved</span><span>Denied</span><span>Damaged (ha)</span><span>Most requested</span>
            </div>
            <div class="divider mt-[8px]"></div>
            @forelse ($stats[$key] as $row)
                <div class="grid h-[40px] {{ $cols }} items-center pl-[4px] text-[14px] font-bold">
                    <span class="truncate pr-[12px]">{{ $row['name'] }}</span>
                    <span>{{ $row['requests'] }}</span>
                    <span>{{ $row['farmers'] }}</span>
                    <span>{{ $row['pending'] }}</span>
                    <span>{{ $row['approved'] }}</span>
                    <span>{{ $row['denied'] }}</span>
                    <span>{{ rtrim(rtrim(number_format($row['damaged_ha'], 2), '0'), '.') }}</span>
                    <span class="truncate">{{ $row['top'] }}</span>
                </div>
            @empty
                <p class="py-[12px] pl-[4px] text-[13px] font-bold">No requests match these filters.</p>
            @endforelse
        </x-ui.card>
    @endforeach

    <x-ui.card title="Requests">
        <div class="mt-[12px] grid {{ $listCols }} pl-[4px] text-[13px] font-medium text-muted" aria-hidden="true">
            <span>Filed</span><span>Farmer</span><span>Barangay</span><span>Program</span><span>Qty</span><span>Crisis</span><span class="text-center">Status</span><span>{{ $canDecide ? 'Decision' : 'Note' }}</span>
        </div>
        <div class="divider mt-[8px]"></div>
        @forelse ($requests as $item)
            <div class="grid min-h-[48px] {{ $listCols }} items-center gap-y-[4px] py-[4px] pl-[4px] text-[14px] font-bold">
                <span>{{ $item->created_at->format('M j, Y') }}</span>
                <a href="{{ route('beneficiaries.show', $item->beneficiary) }}" class="truncate pr-[12px] hover:text-brand hover:underline">{{ $item->beneficiary->fullName() }}</a>
                <span class="truncate pr-[12px]">{{ $item->beneficiary->barangay?->name }}</span>
                <span class="truncate pr-[12px]" title="{{ $item->reason }}">{{ $item->intervention->sourcedName() }}</span>
                <span>{{ $item->quantity === null ? '—' : rtrim(rtrim((string) $item->quantity, '0'), '.') }}</span>
                <span class="truncate pr-[12px]">{{ $item->disaster?->name ?? '—' }}</span>
                <x-ui.status-chip :tone="$item->statusTone()">{{ $item->statusLabel() }}</x-ui.status-chip>
                @if ($canDecide && $item->isPending())
                    <div class="flex items-center gap-[8px] pl-[12px]">
                        <form method="POST" action="{{ route('assistance-requests.approve', $item) }}">
                            @csrf
                            <button type="submit" class="bg-brand-bar gradient-button h-[32px] rounded-[50px] px-[14px] text-[13px] font-bold text-white">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('assistance-requests.deny', $item) }}" class="flex min-w-0 flex-1 items-center gap-[6px]">
                            @csrf
                            <input type="text" name="decision_note" maxlength="500" required placeholder="Reason to deny" aria-label="Reason to deny"
                                   class="h-[32px] min-w-0 flex-1 rounded-[10px] border border-field bg-white px-[10px] text-[13px] font-medium outline-none placeholder:text-muted">
                            <button type="submit" class="h-[32px] rounded-[50px] border-[1.5px] border-bad bg-white px-[12px] text-[13px] font-bold">Deny</button>
                        </form>
                    </div>
                @else
                    <span class="truncate pl-[12px] text-[13px] font-medium text-muted" title="{{ $item->decision_note ?? $item->reason }}">{{ $item->decision_note ?? $item->reason ?? '—' }}</span>
                @endif
            </div>
        @empty
            <p class="py-[12px] pl-[4px] text-[13px] font-bold">No requests match these filters.</p>
        @endforelse
        <div class="mt-[12px] text-[14px]">{{ $requests->links() }}</div>
    </x-ui.card>
</x-layouts.app>
