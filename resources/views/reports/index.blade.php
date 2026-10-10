@php
    use App\Support\ReportCriteria;

    $pill = 'relative block h-[44px] rounded-[50px] border-[1.5px] bg-white';
    $field = 'h-full w-full cursor-pointer appearance-none rounded-[50px] bg-transparent pr-[40px] pl-[14.5px] text-[14px] font-medium text-muted outline-none focus:text-ink';
    $label = 'mb-[6px] block text-[14px] font-bold leading-[20px]';
    $border = fn (string $name) => $errors->has($name) ? 'border-bad' : 'border-black/15';
    $arrow = asset('images/figma/icons/arrow-down.svg');
    // A forged array in old input falls back to the default.
    $old = fn (string $field, $default = '') => is_scalar($input = old($field)) ? (string) $input : $default;
@endphp
<x-layouts.app title="DOWNLOAD REPORTS">
    {{-- Figma 340:51 (Admin) / 407:1694 (Agri Tech) / 470:2205 (Data Encoder) --}}
    @if ($errors->has('report'))
        <p class="flash rounded-[10px] border-[1.5px] border-bad bg-white px-[16px] py-[10px] text-[14px] font-bold text-danger" role="alert">{{ $errors->first('report') }}</p>
    @endif

    <x-ui.card title="Generate a Report" class="pb-[29px]">
        {{-- The file downloads without leaving the page: block a second click for a while so one click makes one report. --}}
        <form method="POST" action="{{ route('reports.store') }}" class="mt-[19px] grid grid-cols-[707px_710px] gap-x-[19px] gap-y-[6px] pl-[1px]"
              x-data="{ busy: false }" @submit="busy = true; setTimeout(() => busy = false, 15000)" data-busy-reset="15000">
            @csrf
            <div>
                <label for="program" class="{{ $label }}">Program</label>
                <span class="{{ $pill }} {{ $border('program') }}">
                    <select id="program" name="program" class="{{ $field }}">
                        @foreach (ReportCriteria::PROGRAMS as $value => $text)
                            <option value="{{ $value }}" @selected($old('program', 'all') === $value)>Program: {{ $text }}</option>
                        @endforeach
                    </select>
                    <img src="{{ $arrow }}" alt="" class="pointer-events-none absolute top-[12.5px] right-[17px] size-[18px]">
                </span>
                @error('program')<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="format" class="{{ $label }}">Export Format</label>
                <span class="{{ $pill }} {{ $border('format') }}">
                    <select id="format" name="format" class="{{ $field }}">
                        @foreach (ReportCriteria::FORMATS as $value => $text)
                            <option value="{{ $value }}" @selected($old('format', 'pdf') === $value)>Format: {{ $text }}</option>
                        @endforeach
                    </select>
                    <img src="{{ $arrow }}" alt="" class="pointer-events-none absolute top-[12.5px] right-[17px] size-[18px]">
                </span>
                @error('format')<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
            </div>
            @foreach (['start_date' => 'Start Date', 'end_date' => 'End Date'] as $name => $text)
                <div>
                    <label for="{{ $name }}" class="{{ $label }}">{{ $text }}</label>
                    <span class="{{ $pill }} {{ $border($name) }}">
                        <input id="{{ $name }}" type="date" name="{{ $name }}" value="{{ $old($name) }}" max="{{ now()->toDateString() }}" class="{{ $field }} cursor-text pr-[16px]">
                    </span>
                    @error($name)<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div>
                <label for="distribution_cycle_id" class="{{ $label }}">Distribution Cycle</label>
                <span class="{{ $pill }} {{ $border('distribution_cycle_id') }}">
                    <select id="distribution_cycle_id" name="distribution_cycle_id" required class="{{ $field }}">
                        @foreach ($cycles as $cycle)
                            <option value="{{ $cycle->id }}" @selected($old('distribution_cycle_id', (string) $currentCycleId) === (string) $cycle->id)>Cycle: {{ $cycle->label }}</option>
                        @endforeach
                    </select>
                    <img src="{{ $arrow }}" alt="" class="pointer-events-none absolute top-[12.5px] right-[17px] size-[18px]">
                </span>
                @error('distribution_cycle_id')<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
            </div>
            <p class="self-end pb-[12px] text-[12px] font-medium leading-[16px] text-muted">Reports cover one distribution cycle. The dates narrow claimed distributions; unclaimed records are always listed.</p>

            <button type="submit" :disabled="busy" class="bg-brand-bar gradient-button col-span-2 mt-[6px] flex h-[48px] items-center justify-center gap-[4px] rounded-[50px] text-[20px] font-bold leading-[24px] text-white disabled:opacity-70">
                <img src="{{ asset('images/figma/icons/plus.svg') }}" alt="" class="size-[21px] brightness-0 invert">
                <span x-text="busy ? 'Generating…' : 'Generate Report'">Generate Report</span>
            </button>
            <span class="sr-only">Generating…</span>
        </form>
    </x-ui.card>

    {{-- Not in Figma: generated files, below the mirrored card (spec §4 generated_reports). --}}
    <x-ui.card title="Generated Reports">
        <div class="mt-[16px] grid grid-cols-[190px_220px_230px_160px_230px_110px_1fr] text-[14px] font-medium leading-[20px] text-muted" aria-hidden="true">
            <span>Generated</span><span>By</span><span>Cycle</span><span>Program</span><span>Dates</span><span>Format</span><span></span>
        </div>
        <div class="divider mt-[13px]"></div>
        <div role="list">
            @forelse ($history as $report)
                @php $labels = $report->criteria()->labels(); @endphp
                <div role="listitem" class="grid min-h-[45px] grid-cols-[190px_220px_230px_160px_230px_110px_1fr] items-center text-[14px] font-bold leading-[20px]">
                    <span>{{ $report->created_at->format('M j, Y g:i A') }}</span>
                    <span class="truncate pr-[12px]">{{ $report->user?->name }}</span>
                    <span class="truncate pr-[12px]">{{ $labels['cycle'] }}</span>
                    <span>{{ $labels['program'] }}</span>
                    <span>{{ $labels['dates'] }}</span>
                    <span>{{ strtoupper($report->format) === 'XLSX' ? 'Excel' : 'PDF' }}</span>
                    <a href="{{ route('reports.download', $report) }}" class="justify-self-end text-brand hover:underline">Download</a>
                </div>
            @empty
                <p class="py-[14px] text-[14px] font-bold">No reports generated yet.</p>
            @endforelse
        </div>
        @if ($history->hasPages())
            <div class="mt-[12px]">{{ $history->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
