@php
    use App\Services\DamageCalculator;

    $cols = 'grid-cols-[220px_150px_142px_137px_231px_108px_150px_1fr_140px]';
    $filterBox = 'relative block h-[44px] shrink-0 rounded-[10px] border border-field bg-white';
    $filterSelect = 'h-full w-full cursor-pointer appearance-none bg-transparent pr-[36px] pl-[16px] text-[14px] font-bold leading-[20px] outline-none';
    $cards = [
        ['Farmers Affected', DamageCalculator::compact((float) $summary->farmers), 'bg-stat-1', 'text-stat-1'],
        ['Total Area Damaged (ha)', DamageCalculator::compact((float) $summary->area), 'bg-stat-2', 'text-stat-2'],
        ['Production Loss (MT)', DamageCalculator::compact((float) $summary->loss), 'bg-stat-4', 'text-stat-4'],
        ['Est. Cost of Damage (₱)', DamageCalculator::compact((float) $summary->cost), 'bg-stat-3', 'text-stat-3'],
    ];
    $exportQuery = $filters->toQuery();
@endphp
<x-layouts.app title="CRISIS REPORTS">
    {{-- Figma 329:2978 (Admin) / 407:1554 (Agri Tech) / 470:1863 (Data Encoder) --}}
    <x-intervention.flash />

    <div x-data="{ photos: [], index: 0 }" @open-gallery.window="photos = $event.detail; index = 0" @keydown.escape.window="photos = []">
        <x-ui.card title="Crisis / Crop Damage Report" class="pb-[22px]">
            <div class="divider mt-[15px] ml-[3px]"></div>

            <form method="GET" class="mt-[17px] flex items-center gap-[20px] pl-[1.5px]">
                <label class="{{ $filterBox }} w-[280px]">
                    <span class="sr-only">Crisis</span>
                    <select name="disaster" onchange="this.form.requestSubmit()" class="{{ $filterSelect }}">
                        <option value="all" @selected($filters->disasterId === null)>Crisis: All</option>
                        @foreach ($filters->disasters as $disaster)
                            <option value="{{ $disaster->id }}" @selected($filters->disasterId === $disaster->id)>Crisis: {{ $disaster->name }}</option>
                        @endforeach
                    </select>
                    <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="pointer-events-none absolute top-[13px] right-[12px] size-[18px]">
                </label>
                <label class="{{ $filterBox }} w-[240px]">
                    <span class="sr-only">Barangay</span>
                    <select name="barangay" onchange="this.form.requestSubmit()" class="{{ $filterSelect }}">
                        <option value="all">Barangay: All</option>
                        @foreach ($filters->barangays as $id => $name)
                            <option value="{{ $id }}" @selected($filters->barangayId === $id)>Barangay: {{ $name }}</option>
                        @endforeach
                    </select>
                    <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="pointer-events-none absolute top-[13px] right-[12px] size-[18px]">
                </label>
                <label class="{{ $filterBox }} w-[220px]">
                    <span class="sr-only">Status</span>
                    <select name="status" onchange="this.form.requestSubmit()" class="{{ $filterSelect }}">
                        @foreach ($filters->statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected($filters->status === $value)>Status: {{ $label }}</option>
                        @endforeach
                    </select>
                    <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="pointer-events-none absolute top-[13px] right-[12px] size-[18px]">
                </label>
                <button type="submit" class="sr-only">Apply filters</button>
                @can('damage.create')
                    <a href="{{ route('damage.create') }}"
                       class="ml-auto flex h-[44px] w-[260px] items-center justify-center rounded-[10px] bg-brand-soft text-[14px] font-bold text-white transition-colors hover:bg-[#4b32c3]">+ New Damage Report</a>
                @endcan
            </form>

            {{-- Figma 423:133–148 --}}
            <div class="mt-[16px] grid grid-cols-4 gap-[20px] pl-[1.5px]">
                @foreach ($cards as [$label, $value, $dot, $text])
                    <div class="h-[108px] rounded-[16px] bg-[#f7f7f9] pt-[18px] pl-[20px]">
                        <p class="flex items-center gap-[7px] text-[13px] leading-[17px]"><span class="size-[11px] rounded-[3px] {{ $dot }}"></span>{{ $label }}</p>
                        <p class="mt-[5px] text-[30px] font-bold leading-[39px] tabular-nums {{ $text }}" data-count-up>{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Graphical summary of the same filtered reports (paper 1.4.1). --}}
            <div class="mt-[20px] grid grid-cols-2 gap-[20px] pl-[1.5px]">
                <x-damage.bar-chart title="Cost of Damage by Barangay" :bars="$charts['barangays']" />
                <x-damage.bar-chart title="Cost of Damage by Crop" :bars="$charts['crops']" />
            </div>

            <h3 class="mt-[22px] pl-[1.5px] text-[16px] font-bold leading-[20px]">Reported Damage Records</h3>
            <div class="mt-[10px] ml-[1.5px] h-[2px] bg-[#ececec]"></div>
            <div class="mt-[16px] grid {{ $cols }} pl-[1.5px] text-[14px] font-medium leading-[20px] text-muted" aria-hidden="true">
                <span>Name</span><span>Barangay</span><span>Crop / Farm Loc.</span><span>Crop Stage</span><span>Damaged Area (Total/Partial)</span>
                <span>Loss (MT)</span><span>Cost of Damage</span><span>Photos</span><span>Status</span>
            </div>

            <div role="list" class="mt-[2px]">
                @forelse ($reports as $report)
                    <div role="listitem" class="hover-tint -mx-[8px] grid min-h-[42px] {{ $cols }} items-center rounded-[10px] py-[4px] pr-[8px] pl-[9.5px] text-[13px] font-bold leading-[20px]">
                        <a href="{{ route('damage.show', $report) }}" class="truncate pr-[12px] hover:underline">{{ $report->beneficiary->fullName() }}</a>
                        <span class="truncate pr-[12px]">{{ $report->barangay->name }}</span>
                        <span class="truncate pr-[12px]" title="{{ $report->farm_location }}">{{ $report->crop->name }}@if ($report->farm_location)<span class="block truncate text-[12px] font-medium text-muted">{{ $report->farm_location }}</span>@endif</span>
                        <span>{{ $report->stageLabel() }}</span>
                        <span>{{ $report->areaLabel() }}</span>
                        <span>{{ DamageCalculator::decimal((float) $report->loss_mt) }} MT</span>
                        <span>₱{{ DamageCalculator::decimal((float) $report->cost) }}</span>
                        @if ($report->photos_count > 0)
                            <button type="button" class="w-fit text-left hover:underline"
                                    data-photos="{{ $report->photos->map(fn ($photo) => route('damage.photos.show', $photo))->join(' ') }}"
                                    @click="$dispatch('open-gallery', $el.dataset.photos.split(' '))">{{ $report->photos_count }} <span class="text-muted">(view)</span></button>
                        @else
                            <span>0</span>
                        @endif
                        <x-damage.status :report="$report" />
                    </div>
                @empty
                    <p class="py-[14px] pl-[1.5px] text-[14px] font-bold">No damage reports for this filter yet.</p>
                @endforelse
            </div>

            <div class="mt-[22px] flex items-center gap-[20px] pl-[1.5px]">
                <div class="min-w-0 flex-1">
                    @if ($reports->hasPages())
                        {{ $reports->links() }}
                    @else
                        <p class="text-[13px] leading-[17px] text-muted">Showing {{ $reports->count() }} of {{ $reports->total() }} {{ Str::plural('record', $reports->total()) }}</p>
                    @endif
                </div>
                @can('export.run')
                    <a href="{{ Route::has('damage.export') ? route('damage.export', $exportQuery) : '#' }}" data-busy
                       class="flex h-[40px] w-[220px] shrink-0 items-center justify-center rounded-[10px] border border-field bg-white text-[13px] font-bold transition-colors hover:border-brand-soft hover:text-brand">Export to Excel</a>
                @endcan
                <a href="{{ Route::has('damage.pdf') ? route('damage.pdf', $exportQuery) : '#' }}"
                   class="flex h-[40px] w-[220px] shrink-0 items-center justify-center rounded-[10px] bg-brand-soft text-[13px] font-bold text-white transition-colors hover:bg-[#4b32c3]">Generate PDF Report</a>
            </div>
        </x-ui.card>

        @can('damage.configure')
            {{-- Not in Figma: kept below the mirrored card (spec §2). Two buttons, so adding a crisis is easy to find. --}}
            <div class="mt-[14px] flex gap-[14px]">
                <button type="button" @click="$dispatch('open-modal', 'add-crisis')"
                        class="border-gradient pill-button h-[39px] w-[220px] rounded-[50px] text-[18px] font-bold leading-[24px]">+ Add Crisis</button>
                <button type="button" @click="$dispatch('open-modal', 'crop-values')"
                        class="border-gradient pill-button h-[39px] w-[220px] rounded-[50px] text-[18px] font-bold leading-[24px]">Crop Values</button>
            </div>

            @php
                $referenceErrors = $errors->reference;
                // Both forms report into one error bag: reopen the window whose fields had the mistake.
                $crisisFailed = $referenceErrors->hasAny(['name', 'occurred_on']);
                $cropFailed = $referenceErrors->any() && ! $crisisFailed;
                $cell = 'h-[36px] w-full rounded-[8px] border border-field bg-white px-[10px] text-[14px] font-bold outline-none focus:border-brand-soft';
                $errorList = 'mb-[12px] rounded-[10px] border-[1.5px] border-bad px-[14px] py-[8px] text-[13px] font-semibold text-danger';
            @endphp

            <x-ui.modal name="add-crisis" title="Add Crisis" :open="$crisisFailed" width="640">
                @if ($crisisFailed)
                    <ul class="{{ $errorList }}" role="alert">
                        @foreach ($referenceErrors->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('damage.disasters.store') }}">
                    @csrf
                    <p class="text-[13px] font-medium text-muted">A new crisis (e.g. a typhoon or a pest outbreak) appears in the Crisis filter and on the File Crisis Report form.</p>
                    <div class="mt-[10px] grid grid-cols-[1fr_190px] gap-[10px]">
                        <input type="text" name="name" maxlength="100" required placeholder="e.g. Rain-Induced Landslide" value="{{ old('name') }}" aria-label="Crisis name" class="{{ $cell }}">
                        <input type="date" name="occurred_on" required max="{{ now()->toDateString() }}" value="{{ old('occurred_on') }}" aria-label="Date it struck" class="{{ $cell }}">
                    </div>
                    <button type="submit" class="bg-brand-bar gradient-button mt-[14px] h-[44px] w-full rounded-[50px] text-[18px] font-bold text-white">Add Crisis</button>
                </form>

                <h3 class="mt-[20px] text-[14px] font-bold">Crises already in AGAPAY</h3>
                <ul class="mt-[6px] max-h-[180px] overflow-y-auto rounded-[10px] bg-[#f7f7f9] px-[14px] py-[8px] text-[13px]">
                    @forelse ($crises as $crisis)
                        <li class="flex justify-between py-[3px]"><span class="font-bold">{{ $crisis->name }}</span><span class="text-muted">{{ $crisis->occurred_on?->format('M j, Y') }}</span></li>
                    @empty
                        <li class="text-muted">No crises yet.</li>
                    @endforelse
                </ul>
            </x-ui.modal>

            <x-ui.modal name="crop-values" title="Crop Values" :open="$cropFailed" width="900">
                <div class="max-h-[60vh] overflow-y-auto pr-[4px]">
                    @if ($cropFailed)
                        <ul class="{{ $errorList }}" role="alert">
                            @foreach ($referenceErrors->all() as $message)<li>{{ $message }}</li>@endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ route('damage.crops.update') }}">
                        @csrf
                        @method('PUT')
                        <p class="text-[13px] font-medium text-muted">Loss = (Total + factor × Partial) × Yield · Cost = Loss × Price. Filed reports keep the values they were computed with.</p>
                        <div class="mt-[10px] grid grid-cols-[1fr_170px_200px_170px] gap-x-[10px] gap-y-[8px] text-[13px] font-medium text-muted">
                            <span>Crop</span><span>Yield (MT/ha)</span><span>Farmgate Price (₱/MT)</span><span>Partial Factor (0–1)</span>
                            @foreach ($crops as $crop)
                                <span class="self-center text-[14px] font-bold text-ink">{{ $crop->name }}</span>
                                @foreach (['yield_mt_per_ha', 'price_per_mt', 'partial_loss_factor'] as $field)
                                    <input type="text" inputmode="decimal" name="crops[{{ $crop->id }}][{{ $field }}]" aria-label="{{ $crop->name }} {{ $field }}"
                                           value="{{ old("crops.{$crop->id}.{$field}", rtrim(rtrim((string) $crop->{$field}, '0'), '.')) }}" class="{{ $cell }}">
                                @endforeach
                            @endforeach
                            <input type="text" name="new_crop[name]" maxlength="50" placeholder="+ Add a crop" value="{{ old('new_crop.name') }}" class="{{ $cell }}">
                            @foreach (['yield_mt_per_ha' => 'e.g. 4', 'price_per_mt' => 'e.g. 20000', 'partial_loss_factor' => '0.5'] as $field => $placeholder)
                                <input type="text" inputmode="decimal" name="new_crop[{{ $field }}]" placeholder="{{ $placeholder }}" value="{{ old("new_crop.{$field}") }}" class="{{ $cell }}">
                            @endforeach
                        </div>
                        <button type="submit" class="bg-brand-bar gradient-button mt-[14px] h-[44px] w-full rounded-[50px] text-[18px] font-bold text-white">Save Crop Values</button>
                    </form>
                </div>
            </x-ui.modal>
        @endcan

        {{-- Photo viewer for "(view)" --}}
        <div x-cloak x-show="photos.length" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" @click.self="photos = []"
             role="dialog" aria-modal="true" aria-label="Damage photos">
            <div class="relative flex max-h-[calc(var(--app-vh,100vh)*0.9)] max-w-[90%] flex-col items-center gap-[12px]">
                <img :src="photos[index]" alt="Damage photo" class="max-h-[calc(var(--app-vh,100vh)*0.8)] max-w-full rounded-[12px] bg-white object-contain">
                <div class="flex items-center gap-[16px] text-[14px] font-bold text-white">
                    <button type="button" @click="index = (index + photos.length - 1) % photos.length" x-show="photos.length > 1" class="rounded-full bg-white/20 px-[14px] py-[6px] hover:bg-white/30">‹ Prev</button>
                    <span x-text="(index + 1) + ' / ' + photos.length"></span>
                    <button type="button" @click="index = (index + 1) % photos.length" x-show="photos.length > 1" class="rounded-full bg-white/20 px-[14px] py-[6px] hover:bg-white/30">Next ›</button>
                    <button type="button" @click="photos = []" class="rounded-full bg-white/20 px-[14px] py-[6px] hover:bg-white/30">Close</button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
