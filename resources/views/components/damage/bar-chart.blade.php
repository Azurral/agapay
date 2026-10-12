@props(['title', 'bars'])
{{-- Horizontal bar chart (App\Support\DamageCharts): cost of damage per group, the largest bar full width. --}}
<figure {{ $attributes->class('rounded-[16px] bg-[#f7f7f9] px-[20px] pt-[16px] pb-[18px]') }}>
    <figcaption class="text-[14px] font-bold leading-[18px]">{{ $title }}</figcaption>
    @if (empty($bars))
        <p class="mt-[12px] text-[13px] font-medium text-muted">No damage to chart for these filters.</p>
    @else
        <ul class="mt-[12px] flex flex-col gap-[9px]">
            @foreach ($bars as $bar)
                <li class="grid grid-cols-[120px_1fr_170px] items-center gap-[12px] text-[13px] leading-[17px]">
                    <span class="truncate font-bold" title="{{ $bar['name'] }}">{{ $bar['name'] }}</span>
                    <span class="h-[14px] overflow-hidden rounded-full bg-black/5">
                        <span class="damage-bar block h-full rounded-full" style="width: {{ max($bar['percent'], 1) }}%; --i: {{ $loop->index }}"></span>
                    </span>
                    <span class="text-right tabular-nums">
                        <span class="font-bold">₱{{ \App\Services\DamageCalculator::decimal($bar['value']) }}</span>
                        <span class="text-muted"> · {{ $bar['farmers'] }} {{ \Illuminate\Support\Str::plural('farmer', $bar['farmers']) }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</figure>
