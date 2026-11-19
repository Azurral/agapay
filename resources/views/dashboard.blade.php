@php
    $has = fn (string $id) => in_array($id, $widgets, true);
    $panels = array_values(array_filter($widgets, fn ($id) => \App\Support\DashboardWidgets::group($id) === 'panel'));
@endphp
<x-layouts.app title="HOME">
    @if (session('status'))
        <p class="flash mb-[14px] rounded-[10px] border-[1.5px] border-ok bg-white px-[16px] py-[10px] text-[14px] font-bold" role="status" data-autohide>{{ session('status') }}</p>
    @endif

    {{-- The user's chosen widgets, in their order (Customize); until they choose, their role's defaults. --}}
    <x-dashboard.overview :overview="$overview" :widgets="$widgets" :allowed="$allowed" :details="$details">
        @if ($panels !== [])
            <div class="mt-[14px] grid grid-cols-2 gap-[14px]">
                @foreach ($panels as $panel)
                    <section class="rounded-[16px] bg-[#f7f7f9] px-[18px] pt-[14px] pb-[16px] text-[13px]">
                        @if ($panel === 'walkins')
                            <p class="flex justify-between text-[14px] font-bold"><span>Walk-in registrations waiting</span><span class="tabular-nums">{{ $walkins->count() }}</span></p>
                            <ul class="mt-[8px] flex flex-col gap-[4px]">
                                @forelse ($walkins->take(5) as $farmer)
                                    <li><a href="{{ route('beneficiaries.show', $farmer) }}" class="font-bold hover:text-brand">{{ $farmer->fullName() }}</a>
                                        <span class="text-arrow">&middot; {{ $farmer->barangay?->name }} &middot; {{ $farmer->created_at->format('M j') }}</span></li>
                                @empty
                                    <li class="text-arrow">None waiting.</li>
                                @endforelse
                            </ul>
                            @if ($walkins->isNotEmpty())
                                <a href="{{ route('beneficiaries.approvals') }}" class="mt-[8px] inline-block font-bold text-brand hover:underline">Open Walk-in Approvals &rsaquo;</a>
                            @endif
                        @elseif ($panel === 'claiming')
                            <p class="text-[14px] font-bold">Next claiming dates</p>
                            <ul class="mt-[8px] flex flex-col gap-[4px]">
                                @forelse ($claimingNext as $cycle)
                                    <li><a href="{{ route('claiming.show', $cycle) }}" class="font-bold hover:text-brand">{{ $cycle->claimingLabel() }}</a>
                                        <span class="text-arrow">&middot; {{ $cycle->label }}{{ $cycle->venue ? ' · '.$cycle->venue : '' }}</span></li>
                                @empty
                                    <li class="text-arrow">No claiming date is scheduled yet.</li>
                                @endforelse
                            </ul>
                            <a href="{{ route('claiming.index') }}" class="mt-[8px] inline-block font-bold text-brand hover:underline">Claiming Calendar &rsaquo;</a>
                        @elseif ($panel === 'damage')
                            <p class="text-[14px] font-bold">Latest crisis damage</p>
                            @if ($damage)
                                <p class="mt-[6px]"><b>{{ $damage['crisis']->name }}</b> <span class="text-arrow">&middot; {{ $damage['crisis']->occurred_on?->format('M j, Y') }}</span></p>
                                <p class="mt-[4px] tabular-nums">{{ $damage['farmers'] }} {{ \Illuminate\Support\Str::plural('farmer', $damage['farmers']) }} affected &middot;
                                    <b>₱{{ \App\Services\DamageCalculator::decimal($damage['cost']) }}</b> estimated &middot; {{ $damage['validated'] }} of {{ $damage['reports'] }} reports validated</p>
                                <a href="{{ route('damage.index', ['disaster' => $damage['crisis']->id]) }}" class="mt-[8px] inline-block font-bold text-brand hover:underline">Crisis Reports &rsaquo;</a>
                            @else
                                <p class="mt-[6px] text-arrow">No crisis recorded yet.</p>
                            @endif
                        @elseif ($panel === 'to_verify')
                            <p class="text-[14px] font-bold">Unclaimed records to verify after claiming</p>
                            <ul class="mt-[8px] flex flex-col gap-[4px]">
                                @forelse ($toVerify as $row)
                                    <li><a href="{{ route('claiming.show', $row['cycle']) }}" class="font-bold hover:text-brand">{{ $row['cycle']->code }}</a>
                                        <span class="text-arrow">&middot; {{ $row['count'] }} {{ \Illuminate\Support\Str::plural('record', $row['count']) }} to forward or close</span></li>
                                @empty
                                    <li class="text-arrow">Nothing to verify.</li>
                                @endforelse
                            </ul>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </x-dashboard.overview>

    @foreach ($widgets as $widget)
        @if ($widget === 'encoding_queue')
            {{-- Figma 237:1659 --}}
            <x-ui.card title="Pending Encoding Queue" class="mb-[20px]">
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
        @elseif ($widget === 'summary' && $summary)
            {{-- Quarterly and yearly summary (panel): each quarter of the chosen year and the whole year. --}}
            <x-ui.card title="Quarterly and Yearly Summary" class="mb-[20px]">
                <x-slot:actions>
                    <form method="GET" class="-mt-[6px]">
                        <label class="text-[13px] font-bold">Year
                            <select name="year" onchange="this.form.submit()" aria-label="Year of the summary"
                                    class="ml-[6px] h-[34px] cursor-pointer rounded-[50px] border-[1.5px] border-brand-soft bg-white px-[12px] text-[13px] font-bold">
                                @foreach ($summary['years'] as $year)
                                    <option value="{{ $year }}" @selected($year === $summary['year'])>{{ $year }}</option>
                                @endforeach
                            </select>
                        </label>
                    </form>
                </x-slot:actions>
                <table class="mt-[14px] w-full text-[14px]">
                    <thead>
                        <tr class="text-left text-[13px] text-muted">
                            <th class="py-[6px] font-medium">{{ $summary['year'] }}</th>
                            @foreach (['Q1' => 'Jan–Mar', 'Q2' => 'Apr–Jun', 'Q3' => 'Jul–Sep', 'Q4' => 'Oct–Dec'] as $q => $months)
                                <th class="py-[6px] text-right font-medium">{{ $q }} <span class="block text-[11px]">{{ $months }}</span></th>
                            @endforeach
                            <th class="py-[6px] pr-[8px] text-right font-bold text-ink">Whole year</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['rows'] as $row)
                            <tr class="border-t border-black/5">
                                <td class="py-[9px] font-bold">
                                    @if ($row['link'] && auth()->user()->can($row['permission']))
                                        <a href="{{ $row['link'] }}" class="hover:text-brand hover:underline">{{ $row['label'] }} &rsaquo;</a>
                                    @else
                                        {{ $row['label'] }}
                                    @endif
                                </td>
                                @foreach ($row['values'] as $i => $value)
                                    <td @class(['py-[9px] text-right font-bold tabular-nums', 'pr-[8px] text-brand' => $i === 4])>
                                        {{ $row['money'] ? '₱'.number_format($value) : number_format($value) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.card>
        @elseif ($widget === 'recent_list')
            <div class="mb-[20px]"><x-beneficiary.table title="All Beneficiaries" :beneficiaries="$recent" /></div>
        @endif
    @endforeach
</x-layouts.app>
