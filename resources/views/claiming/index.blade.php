<x-layouts.app title="CLAIMING CALENDAR">
    @php
        $start = $month->startOfMonth()->startOfWeek(\Carbon\CarbonInterface::SUNDAY);
        $end = $month->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SATURDAY);
        $today = now()->toDateString();
    @endphp
    <x-ui.card title="Claiming Calendar">
        <x-slot:actions>
            <div class="flex items-center gap-[10px] text-[14px] font-bold">
                <a href="{{ route('claiming.index', ['month' => $month->subMonth()->format('Y-m')]) }}" class="border-gradient pill-button rounded-full px-[14px] py-[4px]" aria-label="Previous month">&larr;</a>
                <span class="w-[150px] text-center">{{ $month->format('F Y') }}</span>
                <a href="{{ route('claiming.index', ['month' => $month->addMonth()->format('Y-m')]) }}" class="border-gradient pill-button rounded-full px-[14px] py-[4px]" aria-label="Next month">&rarr;</a>
            </div>
        </x-slot:actions>
        <p class="mt-[8px] text-[13px] text-arrow">The days farmers come to claim their programs. Open a claiming day to print the acknowledgment sheets, upload the signed copies, and verify the records left unclaimed.</p>

        <div class="mt-[12px] grid grid-cols-7 gap-[6px] text-[12px]">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                <div class="text-center font-bold text-arrow">{{ $dow }}</div>
            @endforeach
            @for ($day = $start; $day->lte($end); $day = $day->addDay())
                @php($key = $day->toDateString())
                <div @class(['min-h-[92px] rounded-[12px] border-[1.5px] p-[6px]',
                             'border-black/5 bg-[#f7f7f9]' => $day->month === $month->month,
                             'border-transparent opacity-40' => $day->month !== $month->month,
                             'border-brand-soft!' => $key === $today])>
                    <p class="text-right font-bold {{ $key === $today ? 'text-brand' : '' }}">{{ $day->day }}</p>
                    @foreach ($days[$key] ?? [] as $cycle)
                        <a href="{{ route('claiming.show', $cycle) }}" title="{{ $cycle->label }}{{ $cycle->venue ? ' · '.$cycle->venue : '' }}"
                           class="chart-claimed mt-[4px] block truncate rounded-[6px] px-[6px] py-[2px] font-bold text-white hover:opacity-90">
                            {{ $cycle->code }} claiming
                        </a>
                    @endforeach
                </div>
            @endfor
        </div>
    </x-ui.card>

    <x-ui.card title="Claiming Dates" class="mt-[20px]">
        <div class="divider mt-[12px]"></div>
        @forelse ($cycles as $cycle)
            <a href="{{ route('claiming.show', $cycle) }}" class="hover-tint -mx-[8px] grid grid-cols-[110px_260px_220px_1fr_130px] items-center rounded-[10px] px-[8px] py-[10px] text-[14px]">
                <span class="font-bold">{{ $cycle->code }}</span>
                <span>{{ $cycle->label }}</span>
                <span>{{ $cycle->claimingLabel() }}</span>
                <span class="truncate text-arrow">{{ $cycle->venue }}</span>
                <span class="text-right font-bold {{ $cycle->claimingEnded() ? 'text-arrow' : 'text-brand' }}">{{ $cycle->claimingEnded() ? 'Claiming over' : 'Upcoming / open' }}</span>
            </a>
        @empty
            <p class="py-[12px] text-[14px] font-bold">No distribution cycle has a schedule date yet.</p>
        @endforelse
    </x-ui.card>
</x-layouts.app>
