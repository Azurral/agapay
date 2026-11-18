@props(['overview'])
@php
    $t = $overview['tiles'];
    $percent = $t['assigned'] > 0 ? (int) round($t['claimed'] / $t['assigned'] * 100) : 0;
@endphp
{{-- Home page data presentation (App\Support\DashboardOverview). Every bar also prints its number; hover shows the details. --}}
<x-ui.card title="Overview" class="mb-[20px]">
    <div class="mt-[14px] grid grid-cols-4 gap-[14px]">
        @foreach ([
            ['Registered farmers', number_format($t['farmers']), 'in AGAPAY, all barangays'],
            ['Released in '.($t['cycle'] ?? 'this cycle'), $t['claimed'].' of '.$t['assigned'], $percent.'% of the program records claimed'],
            ['Pending requests', number_format($t['pending_requests']), 'assistance requests waiting for a decision'],
            ['Low stock', number_format($t['low_stock']), $t['low_stock'] === 1 ? 'item at or below its low-stock level' : 'items at or below their low-stock level'],
        ] as [$label, $value, $note])
            <div class="rounded-[16px] bg-[#f7f7f9] px-[18px] py-[14px]">
                <p class="text-[13px] font-bold text-arrow">{{ $label }}</p>
                <p class="mt-[2px] text-[28px] font-bold leading-[34px] tabular-nums">{{ $value }}</p>
                <p class="text-[12px] text-arrow">{{ $note }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-[14px] grid grid-cols-[1.25fr_1fr_1fr] gap-[14px]">
        {{-- Releases of the current cycle per program: claimed / waiting / not claimable, one stacked bar each. --}}
        <figure class="rounded-[16px] bg-[#f7f7f9] px-[18px] pt-[14px] pb-[16px]">
            <figcaption class="flex items-baseline justify-between gap-[8px]">
                <span class="text-[14px] font-bold">Releases in {{ $t['cycle'] ?? 'this cycle' }}</span>
                <span class="flex gap-[12px] text-[12px] text-arrow">
                    <span class="flex items-center gap-[5px]"><i class="chart-swatch chart-claimed"></i>Claimed</span>
                    <span class="flex items-center gap-[5px]"><i class="chart-swatch chart-waiting"></i>Waiting</span>
                    <span class="flex items-center gap-[5px]"><i class="chart-swatch chart-notclaimable"></i>Not claimable</span>
                </span>
            </figcaption>
            @if (empty($overview['programs']))
                <p class="mt-[12px] text-[13px] text-arrow">No program records in the current cycle yet.</p>
            @else
                @php($most = max(array_column($overview['programs'], 'total')))
                <ul class="mt-[12px] flex flex-col gap-[9px]">
                    @foreach ($overview['programs'] as $p)
                        <li class="grid grid-cols-[170px_1fr_58px] items-center gap-[10px] text-[12px] leading-[16px]">
                            <span class="truncate font-bold" title="{{ $p['name'] }}">{{ $p['name'] }}</span>
                            <span class="flex h-[14px] gap-[2px]" style="width: {{ max($p['total'] / $most * 100, 4) }}%">
                                @foreach ([['claimed', 'Claimed', 'chart-claimed'], ['waiting', 'Waiting', 'chart-waiting'], ['not_claimable', 'Not claimable', 'chart-notclaimable']] as [$key, $text, $class])
                                    @if ($p[$key] > 0)
                                        <span class="chart-seg {{ $class }}" style="flex: {{ $p[$key] }}" title="{{ $p['name'] }}: {{ $p[$key] }} {{ strtolower($text) }}"></span>
                                    @endif
                                @endforeach
                            </span>
                            <span class="text-right tabular-nums text-arrow" title="claimed / total">{{ $p['claimed'] }}/{{ $p['total'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </figure>

        {{-- Farmers per barangay: one series, so one colour and no legend. --}}
        <figure class="rounded-[16px] bg-[#f7f7f9] px-[18px] pt-[14px] pb-[16px]">
            <figcaption class="text-[14px] font-bold">Farmers per barangay</figcaption>
            <ul class="mt-[12px] flex flex-col gap-[9px]">
                @forelse ($overview['barangays'] as $b)
                    <li class="grid grid-cols-[96px_1fr_34px] items-center gap-[10px] text-[12px] leading-[16px]" title="{{ $b['name'] }}: {{ $b['value'] }} {{ \Illuminate\Support\Str::plural('farmer', $b['value']) }}">
                        <span class="truncate font-bold">{{ $b['name'] }}</span>
                        <span class="h-[14px]"><span class="chart-seg chart-claimed block h-full" style="width: {{ max($b['percent'], 2) }}%"></span></span>
                        <span class="text-right tabular-nums text-arrow">{{ $b['value'] }}</span>
                    </li>
                @empty
                    <li class="text-[13px] text-arrow">No farmers yet.</li>
                @endforelse
            </ul>
        </figure>

        {{-- Stock: the balance, with a line at the item's low-stock level. Low items say so in words, not colour alone. --}}
        <figure class="rounded-[16px] bg-[#f7f7f9] px-[18px] pt-[14px] pb-[16px]">
            <figcaption class="flex items-baseline justify-between">
                <span class="text-[14px] font-bold">Stock levels</span>
                <span class="flex items-center gap-[5px] text-[12px] text-arrow"><i class="chart-mark-key"></i>low-stock level</span>
            </figcaption>
            <ul class="mt-[12px] flex flex-col gap-[11px]">
                @forelse ($overview['stock'] as $s)
                    @php($amount = \App\Models\InventoryItem::quantity($s['balance']).' '.$s['unit'])
                    <li class="text-[12px] leading-[16px]" title="{{ $s['name'] }}: {{ $amount }} left; low at {{ \App\Models\InventoryItem::quantity($s['threshold']) }}">
                        <span class="flex justify-between gap-[8px]">
                            <span class="truncate font-bold">{{ $s['name'] }}</span>
                            <span class="shrink-0 tabular-nums {{ $s['low'] ? 'font-bold text-danger' : 'text-arrow' }}">{{ $s['low'] ? '⚠ Low · ' : '' }}{{ $amount }}</span>
                        </span>
                        <span class="relative mt-[4px] block h-[10px]">
                            <span class="chart-seg {{ $s['low'] ? 'chart-notclaimable' : 'chart-waiting' }} block h-full" style="width: {{ max($s['percent'], 1) }}%"></span>
                            @if ($s['threshold'] > 0)
                                <span class="chart-mark absolute -top-[3px] h-[16px]" style="left: {{ $s['mark'] }}%"></span>
                            @endif
                        </span>
                    </li>
                @empty
                    <li class="text-[13px] text-arrow">No stock items yet.</li>
                @endforelse
            </ul>
        </figure>
    </div>
</x-ui.card>
