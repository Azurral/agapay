@props(['overview', 'widgets', 'allowed', 'details' => []])
@php
    $t = $overview['tiles'];
    $percent = $t['assigned'] > 0 ? (int) round($t['claimed'] / $t['assigned'] * 100) : 0;
    $tiles = [
        'tile_farmers' => ['Registered farmers', number_format($t['farmers']), 'in AGAPAY, all barangays', 'dash-farmers'],
        'tile_released' => ['Released in '.($t['cycle'] ?? 'this cycle'), $t['claimed'].' of '.$t['assigned'], $percent.'% of the program records claimed', 'dash-releases'],
        'tile_requests' => ['Pending requests', number_format($t['pending_requests']), 'assistance requests waiting for a decision', 'dash-requests'],
        'tile_low_stock' => ['Low stock', number_format($t['low_stock']), $t['low_stock'] === 1 ? 'item at or below its low-stock level' : 'items at or below their low-stock level', 'dash-stock'],
    ];
    $shownTiles = array_values(array_filter($widgets, fn ($id) => isset($tiles[$id])));
    $shownCharts = array_values(array_filter($widgets, fn ($id) => in_array($id, ['chart_releases', 'chart_barangays', 'chart_stock'], true)));
    $chartCols = ['grid-cols-1', 'grid-cols-[1.25fr_1fr]', 'grid-cols-[1.25fr_1fr_1fr]'][max(0, count($shownCharts) - 1)];
@endphp
{{-- Home page data presentation (App\Support\DashboardOverview). Every bar also prints its number; the tiles and bars
     open their details windows (components/dashboard/details). --}}
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
            @php([$label, $value, $note, $modal] = $tiles[$id])
            {{-- A button, like the Crisis Reports cards: opens what the number is made of. --}}
            <button type="button" x-data @click="$dispatch('open-modal', '{{ $modal }}')" aria-haspopup="dialog" data-dash-tile="{{ $modal }}"
                    class="stat-card group relative cursor-pointer rounded-[16px] border-[1.5px] border-transparent bg-[#f7f7f9] px-[18px] py-[14px] text-left transition hover:-translate-y-[2px] hover:border-brand-soft hover:shadow-[0_8px_20px_rgba(90,93,227,0.15)] focus-visible:border-brand-soft focus-visible:outline-none">
                <span class="block text-[13px] font-bold text-arrow">{{ $label }}</span>
                <span class="mt-[2px] block text-[28px] font-bold leading-[34px] tabular-nums">{{ $value }}</span>
                <span class="block pr-[84px] text-[12px] text-arrow">{{ $note }}</span>
                <span class="absolute right-[14px] bottom-[12px] text-[12px] font-bold text-brand opacity-70 group-hover:opacity-100">View details &rsaquo;</span>
            </button>
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
                <ul class="mt-[10px] flex flex-col gap-[3px]" x-data>
                    @foreach ($overview['programs'] as $p)
                        <li><button type="button" data-dash-pick="program" aria-haspopup="dialog" title="Show this program by barangay"
                                @click="$dispatch('dash-pick-program', {{ $p['id'] }}); $dispatch('open-modal', 'dash-program')"
                                class="grid grid-cols-[170px_1fr_58px] items-center gap-[10px] damage-row -mx-[6px] w-[calc(100%+12px)] cursor-pointer rounded-[8px] px-[6px] py-[3px] text-left text-[12px] leading-[16px]">
                            <span class="truncate font-bold" title="{{ $p['name'] }}">{{ $p['name'] }}</span>
                            <span class="flex h-[14px] gap-[2px]" style="width: {{ max($p['total'] / $most * 100, 4) }}%">
                                @foreach ([['claimed', 'Claimed', 'chart-claimed'], ['waiting', 'Waiting', 'chart-waiting'], ['not_claimable', 'Not claimable', 'chart-notclaimable']] as [$key, $text, $class])
                                    @if ($p[$key] > 0)
                                        <span class="chart-seg {{ $class }}" style="flex: {{ $p[$key] }}" title="{{ $p['name'] }}: {{ $p[$key] }} {{ strtolower($text) }}"></span>
                                    @endif
                                @endforeach
                            </span>
                            <span class="text-right tabular-nums text-arrow" title="claimed / total">{{ $p['claimed'] }}/{{ $p['total'] }}</span>
                        </button></li>
                    @endforeach
                </ul>
            @endif
        </figure>
    @elseif ($chart === 'chart_barangays')
        {{-- Farmers per barangay: one series, so one colour and no legend. --}}
        <figure class="rounded-[16px] bg-[#f7f7f9] px-[18px] pt-[14px] pb-[16px]">
            <figcaption class="text-[14px] font-bold">Farmers per barangay</figcaption>
            <ul class="mt-[10px] flex flex-col gap-[3px]" x-data>
                @forelse ($overview['barangays'] as $b)
                    {{-- A barangay opens its details; "Others" opens the table of every barangay. --}}
                    <li><button type="button" data-dash-pick="barangay" aria-haspopup="dialog" title="{{ $b['name'] }}: {{ $b['value'] }} {{ \Illuminate\Support\Str::plural('farmer', $b['value']) }}"
                            @if ($b['id']) @click="$dispatch('dash-pick-barangay', {{ $b['id'] }}); $dispatch('open-modal', 'dash-barangay')" @else @click="$dispatch('open-modal', 'dash-farmers')" @endif
                            class="grid grid-cols-[96px_1fr_34px] items-center gap-[10px] damage-row -mx-[6px] w-[calc(100%+12px)] cursor-pointer rounded-[8px] px-[6px] py-[3px] text-left text-[12px] leading-[16px]">
                        <span class="truncate font-bold">{{ $b['name'] }}</span>
                        <span class="h-[14px]"><span class="chart-seg chart-claimed block h-full" style="width: {{ max($b['percent'], 2) }}%"></span></span>
                        <span class="text-right tabular-nums text-arrow">{{ $b['value'] }}</span>
                    </button></li>
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
            <ul class="mt-[10px] flex flex-col gap-[3px]" x-data>
                @forelse ($overview['stock'] as $s)
                    @php($amount = \App\Models\InventoryItem::quantity($s['balance']).' '.$s['unit'])
                    <li><button type="button" data-dash-pick="item" aria-haspopup="dialog" title="{{ $s['name'] }}: {{ $amount }} left; low at {{ \App\Models\InventoryItem::quantity($s['threshold']) }}"
                            @click="$dispatch('dash-pick-item', {{ $s['id'] }}); $dispatch('open-modal', 'dash-item')"
                            class="block damage-row -mx-[6px] w-[calc(100%+12px)] cursor-pointer rounded-[8px] px-[6px] py-[4px] text-left text-[12px] leading-[16px]">
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
                    </button></li>
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

<x-dashboard.details :details="$details" :overview="$overview" />

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
