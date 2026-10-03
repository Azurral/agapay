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
<x-layouts.app title="AGRICULTURAL DAMAGE REPORT">
    {{-- Figma 329:2978 (Admin) / 407:1554 (Agri Tech) / 470:1863 (Data Encoder) --}}
    <x-intervention.flash />

    <div x-data="{ photos: [], index: 0 }" @open-gallery.window="photos = $event.detail; index = 0" @keydown.escape.window="photos = []">
        <x-ui.card title="Crisis / Crop Damage Report" class="pb-[22px]">
            <div class="divider mt-[15px] ml-[3px]"></div>

            <form method="GET" class="mt-[17px] flex items-center gap-[20px] pl-[1.5px]">
                <label class="{{ $filterBox }} w-[280px]">
                    <span class="sr-only">Disaster</span>
                    <select name="disaster" onchange="this.form.requestSubmit()" class="{{ $filterSelect }}">
                        <option value="all" @selected($filters->disasterId === null)>Disaster: All</option>
                        @foreach ($filters->disasters as $disaster)
                            <option value="{{ $disaster->id }}" @selected($filters->disasterId === $disaster->id)>Disaster: {{ $disaster->name }}</option>
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
                        <p class="mt-[5px] text-[30px] font-bold leading-[39px] {{ $text }}">{{ $value }}</p>
                    </div>
                @endforeach
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
                    <a href="{{ Route::has('damage.export') ? route('damage.export', $exportQuery) : '#' }}"
                       class="flex h-[40px] w-[220px] shrink-0 items-center justify-center rounded-[10px] border border-field bg-white text-[13px] font-bold transition-colors hover:border-brand-soft hover:text-brand">Export to Excel</a>
                @endcan
                <a href="{{ Route::has('damage.pdf') ? route('damage.pdf', $exportQuery) : '#' }}"
                   class="flex h-[40px] w-[220px] shrink-0 items-center justify-center rounded-[10px] bg-brand-soft text-[13px] font-bold text-white transition-colors hover:bg-[#4b32c3]">Generate PDF Report</a>
            </div>
        </x-ui.card>

        @can('damage.configure')
            {{-- Not in Figma: kept below the mirrored card (spec §2). --}}
            <div class="mt-[14px]">
                <button type="button" @click="$dispatch('open-modal', 'damage-reference')"
                        class="border-gradient pill-button h-[39px] w-[262px] rounded-[50px] text-[18px] font-bold leading-[24px]">Disasters &amp; Crop Values</button>
            </div>

            @php
                $referenceErrors = $errors->reference;
                $cell = 'h-[36px] w-full rounded-[8px] border border-field bg-white px-[10px] text-[14px] font-bold outline-none focus:border-brand-soft';
            @endphp
            <x-ui.modal name="damage-reference" title="Disasters & Crop Values" :open="$referenceErrors->any()" width="900">
                <div class="max-h-[60vh] overflow-y-auto pr-[4px]">
                    @if ($referenceErrors->any())
                        <ul class="mb-[12px] rounded-[10px] border-[1.5px] border-bad px-[14px] py-[8px] text-[13px] font-semibold text-danger" role="alert">
                            @foreach ($referenceErrors->all() as $message)<li>{{ $message }}</li>@endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ route('damage.crops.update') }}">
                        @csrf
                        @method('PUT')
                        <h3 class="text-[14px] font-bold">Crop Reference Values</h3>
                        <p class="mt-[2px] text-[13px] font-medium text-muted">Loss = (Total + factor × Partial) × Yield · Cost = Loss × Price. Filed reports keep the values they were computed with.</p>
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

                    <form method="POST" action="{{ route('damage.disasters.store') }}" class="mt-[22px]">
                        @csrf
                        <h3 class="text-[14px] font-bold">Add Disaster</h3>
                        <div class="mt-[10px] grid grid-cols-[1fr_220px_200px] gap-[10px]">
                            <input type="text" name="name" maxlength="100" required placeholder="e.g. Typhoon Egay" value="{{ old('name') }}" aria-label="Disaster name" class="{{ $cell }}">
                            <input type="date" name="occurred_on" required max="{{ now()->toDateString() }}" value="{{ old('occurred_on') }}" aria-label="Date it struck" class="{{ $cell }}">
                            <button type="submit" class="bg-brand-bar gradient-button h-[36px] rounded-[50px] text-[16px] font-bold text-white">Add Disaster</button>
                        </div>
                    </form>
                </div>
            </x-ui.modal>
        @endcan

        {{-- Photo viewer for "(view)" --}}
        <div x-cloak x-show="photos.length" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" @click.self="photos = []"
             role="dialog" aria-modal="true" aria-label="Damage photos">
            <div class="relative flex max-h-[90vh] max-w-[90vw] flex-col items-center gap-[12px]">
                <img :src="photos[index]" alt="Damage photo" class="max-h-[80vh] max-w-[90vw] rounded-[12px] bg-white object-contain">
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
