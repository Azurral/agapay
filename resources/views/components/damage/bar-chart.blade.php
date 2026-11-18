@props(['title', 'bars', 'by' => null])
{{-- Horizontal bar chart (App\Support\DamageCharts): cost of damage per group, the largest bar full width.
     by ('barangay' or 'crop'): each row is a button that opens "Farmers affected" for that group (panel: "so I know who got
     affected"); "Others" lists every name that has no bar of its own. --}}
@php
    $except = array_values(array_filter(array_column($bars, 'name'), fn ($n) => $n !== 'Others'));
@endphp
<figure {{ $attributes->class('rounded-[16px] bg-[#f7f7f9] px-[20px] pt-[16px] pb-[18px]') }}>
    <figcaption class="text-[14px] font-bold leading-[18px]">{{ $title }}@if ($by && ! empty($bars))<span class="ml-[8px] text-[12px] font-medium text-muted">Click a row to see the farmers</span>@endif</figcaption>
    @if (empty($bars))
        <p class="mt-[12px] text-[13px] font-medium text-muted">No damage to chart for these filters.</p>
    @else
        <ul class="mt-[12px] flex flex-col gap-[3px]" x-data>
            @foreach ($bars as $bar)
                @php
                    $cells = 'grid grid-cols-[120px_1fr_170px] items-center gap-[12px] text-[13px] leading-[17px]';
                @endphp
                <li>
                    @if ($by)
                        <button type="button" data-damage-filter="{{ $by }}" aria-haspopup="dialog" title="Show the farmers affected"
                                @click="$dispatch('damage-filter', @js(['by' => $by, 'name' => $bar['name'], 'except' => $except])); $dispatch('open-modal', 'damage-farmers')"
                                class="damage-row {{ $cells }} -mx-[6px] w-[calc(100%+12px)] cursor-pointer rounded-[8px] px-[6px] py-[3px] text-left">
                    @else
                        <div class="{{ $cells }} py-[3px]">
                    @endif
                        <span class="truncate font-bold" title="{{ $bar['name'] }}">{{ $bar['name'] }}</span>
                        <span class="h-[14px] overflow-hidden rounded-full bg-black/5">
                            <span class="damage-bar block h-full rounded-full" style="width: {{ max($bar['percent'], 1) }}%; --i: {{ $loop->index }}"></span>
                        </span>
                        <span class="text-right tabular-nums">
                            <span class="font-bold">₱{{ \App\Services\DamageCalculator::decimal($bar['value']) }}</span>
                            <span @class(['text-muted', 'underline decoration-dotted underline-offset-2' => $by])> · {{ $bar['farmers'] }} {{ \Illuminate\Support\Str::plural('farmer', $bar['farmers']) }}</span>
                        </span>
                    @if ($by)
                        </button>
                    @else
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</figure>
