@props(['overview', 'widgets', 'allowed'])
@php
    $t = $overview['tiles'];
    $percent = $t['assigned'] > 0 ? (int) round($t['claimed'] / $t['assigned'] * 100) : 0;
    $tiles = [
        'tile_farmers' => ['Registered farmers', number_format($t['farmers']), 'in AGAPAY, all barangays'],
        'tile_released' => ['Released in '.($t['cycle'] ?? 'this cycle'), $t['claimed'].' of '.$t['assigned'], $percent.'% of the program records claimed'],
        'tile_requests' => ['Pending requests', number_format($t['pending_requests']), 'assistance requests waiting for a decision'],
        'tile_low_stock' => ['Low stock', number_format($t['low_stock']), $t['low_stock'] === 1 ? 'item at or below its low-stock level' : 'items at or below their low-stock level'],
    ];
    $shownTiles = array_values(array_filter($widgets, fn ($id) => isset($tiles[$id])));
    $shownCharts = array_values(array_filter($widgets, fn ($id) => in_array($id, ['chart_releases', 'chart_barangays', 'chart_stock'], true)));
    $chartCols = ['grid-cols-1', 'grid-cols-[1.25fr_1fr]', 'grid-cols-[1.25fr_1fr_1fr]'][max(0, count($shownCharts) - 1)];
@endphp
{{-- Home page data presentation (App\Support\DashboardOverview). Every bar also prints its number; hover shows the details. --}}
<x-ui.card title="Overview" class="mb-[20px]">
    <x-slot:actions>
        <button type="button" x-data @click="$dispatch('open-modal', 'customize-home')" aria-haspopup="dialog"
                class="border-gradient pill-button -mt-[6px] h-[34px] rounded-[50px] px-[16px] text-[13px] font-bold">&#9881; Customize</button>
    </x-slot:actions>

    @if ($widgets === [])
        <p class="mt-[12px] text-[14px] font-bold">Your home page is empty. Press Customize to choose what to show.</p>
    @endif

    @if ($shownTiles !== [])
    <div class="mt-[14px] grid gap-[14px]" style="grid-template-columns: repeat({{ count($shownTiles) }}, minmax(0, 1fr))">
        @foreach ($shownTiles as $id)
            @php([$label, $value, $note] = $tiles[$id])
            <div class="rounded-[16px] bg-[#f7f7f9] px-[18px] py-[14px]">
                <p class="text-[13px] font-bold text-arrow">{{ $label }}</p>
                <p class="mt-[2px] text-[28px] font-bold leading-[34px] tabular-nums">{{ $value }}</p>
                <p class="text-[12px] text-arrow">{{ $note }}</p>
            </div>
        @endforeach
    </div>
    @endif

    @if ($shownCharts !== [])
    <div class="mt-[14px] grid {{ $chartCols }} gap-[14px]">
    @foreach ($shownCharts as $chart)
    @if ($chart === 'chart_releases')
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
    @elseif ($chart === 'chart_barangays')
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
    @elseif ($chart === 'chart_stock')
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
    @endif
    @endforeach
    </div>
    @endif

    {{ $slot }}
</x-ui.card>

{{-- Customize: tick what Home shows; the arrows change the order. Only widgets the role may see are offered. --}}
<x-ui.modal name="customize-home" title="Customize your home page" width="560">
    <form method="POST" action="{{ route('dashboard.customize') }}" class="-mt-[10px]"
          x-data="{ order: @js(array_values(array_unique([...$widgets, ...array_keys($allowed)]))), on: @js($widgets),
                    move(id, step) { const i = this.order.indexOf(id), j = i + step; if (j < 0 || j >= this.order.length) return; [this.order[i], this.order[j]] = [this.order[j], this.order[i]]; } }">
        @csrf
        @method('PUT')
        <p class="text-[13px] text-muted">Tick what you want to see, in the order you want it. Your choice is saved for your account only.</p>
        <ul class="mt-[12px] flex max-h-[50vh] flex-col gap-[6px] overflow-auto">
            <template x-for="id in order" :key="id">
                <li class="flex items-center gap-[8px] rounded-[10px] bg-[#f7f7f9] px-[10px] py-[6px] text-[13px]">
                    <input type="checkbox" name="widgets[]" :value="id" x-model="on" class="size-[16px] accent-brand" :id="'w-' + id">
                    <label :for="'w-' + id" class="flex-1 cursor-pointer font-bold" x-text="@js($allowed)[id]"></label>
                    <button type="button" @click="move(id, -1)" class="hover-tint rounded-full px-[6px]" aria-label="Move up">&uarr;</button>
                    <button type="button" @click="move(id, 1)" class="hover-tint rounded-full px-[6px]" aria-label="Move down">&darr;</button>
                </li>
            </template>
        </ul>
        {{-- Server-rendered fallback list (also what tests and screen readers see before Alpine starts). --}}
        <noscript>
            @foreach ($allowed as $id => $label)
                <label class="block text-[13px]"><input type="checkbox" name="widgets[]" value="{{ $id }}" @checked(in_array($id, $widgets, true))> {{ $label }}</label>
            @endforeach
        </noscript>
        <div class="mt-[14px] flex gap-[10px]">
            <button type="submit" class="bg-brand-button gradient-button h-[40px] flex-1 rounded-[50px] text-[14px] font-bold text-white">Save</button>
            <button type="submit" name="reset" value="1" class="border-gradient pill-button h-[40px] flex-1 rounded-[50px] text-[13px] font-bold">Reset to the default for my role</button>
        </div>
    </form>
</x-ui.modal>
