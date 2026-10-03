@php
    $uploadError = $errors->first('file') ?: $tooLarge;
    $canConfirm = $batch?->isStaged() && $batch->importableCount() > 0;
    $previewCols = 'grid-cols-[70px_260px_130px_150px_150px_140px_1fr]';
@endphp
<x-layouts.app title="EXCEL IMPORT">
    {{-- Figma 470:540 (Admin) / 430:1887 (Data Encoder) --}}
    <x-intervention.flash />

    <x-ui.card title="Excel Upload" class="h-[362px]">
        <p class="mt-[3px] text-[14px] font-medium leading-[13px] text-muted">Scans incoming spreadsheets and will detect, align, and parse fields despite column shifts or label differences.</p>
        <div class="divider mt-[15px]"></div>

        <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data"
              x-data="{ over: false, busy: false }" @submit="busy = true" class="mt-[16.5px]">
            @csrf
            <label @dragover.prevent="over = true" @dragleave.prevent="over = false"
                   @drop.prevent="over = false; if ($event.dataTransfer.files.length) { $refs.file.files = $event.dataTransfer.files; busy = true; $el.closest('form').submit() }"
                   :class="over ? 'border-brand bg-brand-soft/20' : 'border-black/15 bg-brand-soft/10'"
                   class="relative flex h-[251px] cursor-pointer flex-col items-center rounded-[15px] border-2 border-dashed pt-[100px] text-center transition-colors">
                <input x-ref="file" type="file" name="file" accept=".xlsx,.xls,.csv" class="sr-only" @change="if ($el.files.length) { busy = true; $el.form.submit() }">
                <span class="text-[16px] font-bold leading-[20px]" x-show="! busy">drag &amp; drop .xlsx / .csv files here</span>
                <span class="text-[16px] font-bold leading-[20px]" x-show="busy" x-cloak>Reading the spreadsheet…</span>
                <span class="mt-[2px] text-[13px] leading-[17px] text-black">or click to browse</span>
                @if ($uploadError)
                    <span class="mt-[8px] text-[13px] font-semibold leading-[17px] text-danger" role="alert">{{ $uploadError }}</span>
                @endif
                <span class="bg-brand-bar gradient-button absolute top-[176px] left-1/2 flex h-[39px] w-[263px] -translate-x-1/2 items-center justify-center gap-[2px] rounded-[50px] text-[20px] font-bold leading-[24px] text-white">
                    <img src="{{ asset('images/figma/icons/plus.svg') }}" alt="" class="size-[23px] brightness-0 invert"> Choose File
                </span>
            </label>
        </form>
    </x-ui.card>

    {{-- Figma 470:765 --}}
    <section class="bg-brand-card mt-[7px] rounded-[20px] border-[1.5px] border-black/10 pt-[19.5px] pr-[26.5px] pb-[21px] pl-[21.5px]">
        <header class="flex items-center gap-[5px]">
            <img src="{{ asset('images/figma/icons/task.svg') }}" alt="" class="size-[18px] brightness-0 invert">
            <h2 class="text-[16px] font-bold leading-[20px] text-white">Processing Feedback</h2>
        </header>
        <div class="mt-[16px] min-h-[112px] rounded-[15px] bg-black/25 px-[20px] py-[14px] text-[12px] leading-[21px]">
            @if ($batch)
                @foreach ($batch->feedback as $line)
                    <p @class(['whitespace-pre-wrap', 'text-ok' => $line['ok'], 'text-[#ff7e7e]' => ! $line['ok']])>{{ $line['ok'] ? '✓' : '✕' }}  {{ $line['text'] }}</p>
                @endforeach
            @else
                <p class="text-white">Upload a spreadsheet to see how its rows will be read.</p>
            @endif
        </div>
    </section>

    {{-- Figma 470:760 --}}
    @if ($canConfirm)
        <form method="POST" action="{{ Route::has('import.confirm') ? route('import.confirm', $batch) : '' }}" class="mt-[7px]"
              x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <button type="submit" :disabled="busy"
                    class="bg-brand-bar gradient-button flex h-[59px] w-full items-center justify-center gap-[4px] rounded-[50px] text-[20px] font-bold leading-[24px] text-white">
                <img src="{{ asset('images/figma/icons/plus.svg') }}" alt="" class="size-[21px] brightness-0 invert"> Confirm &amp; Import
            </button>
        </form>
    @else
        <button type="button" disabled data-confirm-disabled
                class="mt-[7px] flex h-[59px] w-full cursor-not-allowed items-center justify-center gap-[4px] rounded-[50px] border-4 border-brand bg-brand-soft/10 opacity-60">
            <img src="{{ asset('images/figma/icons/plus.svg') }}" alt="" class="size-[21px]">
            <span class="text-gradient-heading text-[20px] font-bold leading-[24px]">Confirm &amp; Import</span>
        </button>
    @endif

    {{-- Not in Figma: the staged rows, below the mirrored region (spec §2). --}}
    @if ($batch)
        @php $total = array_sum($batch->counts); @endphp
        <x-ui.card title="Preview" class="mt-[7px]">
            <x-slot:actions>
                @if ($batch->isStaged())
                    <form method="POST" action="{{ route('import.discard', $batch) }}">
                        @csrf
                        <button type="submit" class="border-gradient pill-button h-[39px] w-[174px] rounded-[50px] text-[18px] font-bold leading-[24px]">Discard</button>
                    </form>
                @endif
            </x-slot:actions>

            <p class="mt-[6px] text-[14px] font-medium leading-[20px] text-muted">
                {{ $batch->original_name }} · {{ $total }} {{ Str::plural('row', $total) }} · uploaded {{ $batch->created_at->format('M j, Y g:i A') }}
                @if ($total > $rows->count()) · showing the first {{ $previewLimit }} @endif
            </p>

            <div class="mt-[16px] grid {{ $previewCols }} text-[14px] font-medium leading-[20px] text-muted" aria-hidden="true">
                <span>Row</span><span>Name</span><span>Birthdate</span><span>Barangay</span><span>RSBSA No.</span><span>Status</span><span>Notes</span>
            </div>
            <div class="divider mt-[13px]"></div>
            <div role="list">
                @foreach ($rows as $row)
                    @php $data = $row->data; @endphp
                    <div role="listitem" class="grid min-h-[45px] {{ $previewCols }} items-center border-b border-black/5 py-[6px] text-[14px] font-bold leading-[20px]">
                        <span class="text-muted">{{ $row->row_number }}</span>
                        <span class="truncate pr-[12px]">{{ collect([$data['first_name'] ?? null, $data['middle_name'] ?? null, $data['last_name'] ?? null])->filter()->join(' ') ?: '—' }}</span>
                        <span>{{ ($data['birthdate'] ?? null) ? \Carbon\CarbonImmutable::parse($data['birthdate'])->format('M j, Y') : '—' }}</span>
                        <span class="truncate pr-[12px]">{{ $data['barangay'] ?? '—' }}</span>
                        <span class="truncate pr-[12px]">{{ $data['rsbsa_number'] ?? '—' }}</span>
                        <span>
                            <span @class([
                                'inline-flex h-[28px] items-center rounded-[8px] border-2 bg-white px-[10px] text-[12px]',
                                'border-ok' => $row->statusTone() === 'ok',
                                'border-bad' => $row->statusTone() === 'bad',
                            ])>{{ $row->statusLabel() }}</span>
                        </span>
                        <span class="text-[13px] font-medium {{ $row->status === \App\Models\ImportRow::UNREADABLE ? 'text-danger' : '' }}">{{ implode('; ', $row->issues ?? []) }}</span>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif
</x-layouts.app>
