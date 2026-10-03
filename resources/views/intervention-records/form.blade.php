@php
    $editing = $record !== null;
    $box = 'flex h-[44px] items-center rounded-[10px] border bg-white px-[16px] text-[14px] font-bold leading-[20px]';
    $control = 'ml-[6px] h-full min-w-0 flex-1 bg-transparent font-bold outline-none placeholder:text-muted';
    $source = old('source', $record?->intervention->source ?? '');
    $status = old('distribution_status', $record?->isClaimed() ? 'distributed' : ($editing ? 'not_distributed' : ''));
    $picked = $editing ? null : (old('beneficiary_id') ? \App\Models\Beneficiary::with('barangay')->find(old('beneficiary_id')) : null);
@endphp
<x-layouts.app title="INTERVENTION RECORDS">
    {{-- Figma 446:198 --}}
    <section class="rounded-[20px] border-[1.5px] border-black/10 bg-white pt-[19.5px] pr-[26.5px] pb-[22px] pl-[21.5px]">
        <h2 class="text-[16px] font-bold leading-[20px]">{{ $editing ? 'Edit Intervention Record' : 'Add Intervention Record' }}</h2>
        <div class="divider mt-[10px]"></div>

        <form method="POST" action="{{ $editing ? route('intervention-records.update', $record) : route('intervention-records.store') }}"
              class="mt-[14px] flex flex-col gap-[12px]"
              x-data="{ source: @js($source), status: @js($status), interventions: @js($interventions->map->only(['id', 'source', 'name'])), intervention: @js((string) old('intervention_id', $record?->intervention_id ?? '')) }">
            @csrf
            @if ($editing) @method('PUT') @endif

            {{-- Beneficiary: existing profiles only (spec rule 4). --}}
            @if ($editing)
                <div class="{{ $box }} border-field">
                    <span class="shrink-0">Beneficiary:</span>
                    <span class="ml-[6px]">{{ $record->beneficiary->fullName() }} · {{ $record->beneficiary->rsbsaDisplay() }} · {{ $record->beneficiary->barangay?->name }}</span>
                </div>
            @else
                <div class="relative" x-data="{
                        q: @js($picked ? $picked->fullName() : old('beneficiary_query', '')), id: @js((string) old('beneficiary_id', '')), results: [], open: false,
                        async search() {
                            this.id = '';
                            if (this.q.trim().length < 2) { this.results = []; this.open = false; return; }
                            const res = await fetch(@js(route('beneficiaries.lookup')) + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
                            this.results = res.ok ? await res.json() : []; this.open = true;
                        },
                        pick(r) { this.id = String(r.id); this.q = r.name; this.open = false; },
                    }" @click.outside="open = false">
                    <label @class([$box, 'border-field' => ! $errors->has('beneficiary_id'), 'border-bad' => $errors->has('beneficiary_id')])>
                        <span class="shrink-0">Beneficiary:</span>
                        <input type="text" name="beneficiary_query" x-model="q" @input.debounce.250ms="search()" autocomplete="off"
                               placeholder="Search by name or RSBSA number..." class="{{ $control }}">
                    </label>
                    <input type="hidden" name="beneficiary_id" :value="id">
                    <ul x-cloak x-show="open" class="absolute top-[48px] right-0 left-0 z-30 max-h-[260px] overflow-auto rounded-[10px] border border-field bg-white py-[4px] text-[14px] shadow-[0_8px_24px_rgba(90,93,227,0.18)]">
                        <template x-for="r in results" :key="r.id">
                            <li><button type="button" @click="pick(r)" class="hover-tint flex w-full gap-[10px] px-[16px] py-[8px] text-left">
                                <span class="font-bold" x-text="r.name"></span><span class="text-muted" x-text="r.rsbsa + ' · ' + (r.barangay ?? '')"></span>
                            </button></li>
                        </template>
                        <li x-show="results.length === 0" class="px-[16px] py-[8px] text-muted">No beneficiary found. Register them first under Add Beneficiary.</li>
                    </ul>
                    @error('beneficiary_id')<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="grid grid-cols-2 gap-x-[23px] gap-y-[12px]">
                <div>
                    <label @class([$box, 'border-field' => ! $errors->has('source'), 'border-bad' => $errors->has('source')])>
                        <span class="shrink-0">Program:</span>
                        <select name="source" x-model="source" @change="intervention = ''" required class="{{ $control }} cursor-pointer" :class="source === '' && 'text-muted'">
                            <option value="" disabled>DA / LGU</option>
                            <option value="da">DA - Department of Agriculture</option>
                            <option value="lgu">LGU - Local Government Unit</option>
                        </select>
                    </label>
                    @error('source')<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label @class([$box, 'border-field' => ! $errors->has('intervention_id'), 'border-bad' => $errors->has('intervention_id')])>
                        <span class="shrink-0">Intervention Type:</span>
                        <select name="intervention_id" x-model="intervention" required class="{{ $control }} cursor-pointer" :class="intervention === '' && 'text-muted'">
                            <option value="" disabled x-text="source ? 'Select' : 'Choose a program first'">Select</option>
                            <template x-for="i in interventions.filter(i => i.source === source)" :key="i.id">
                                <option :value="String(i.id)" x-text="i.name" :selected="String(i.id) === intervention"></option>
                            </template>
                        </select>
                    </label>
                    @error('intervention_id')<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>

                <x-ui.inline-field label="Quantity" name="quantity" type="number" step="0.01" min="0" placeholder="e.g. 2"
                                   :value="old('quantity', $record ? rtrim(rtrim((string) $record->quantity, '0'), '.') : '')" :error="$errors->first('quantity')" />
                <div>
                    <label @class([$box, 'border-field' => ! $errors->has('distribution_cycle_id'), 'border-bad' => $errors->has('distribution_cycle_id')])>
                        <span class="shrink-0">Batch / Cycle:</span>
                        <select name="distribution_cycle_id" required class="{{ $control }} cursor-pointer">
                            @foreach ($cycles as $cycle)
                                <option value="{{ $cycle->id }}" @selected((string) old('distribution_cycle_id', $record?->distribution_cycle_id ?? $currentCycleId) === (string) $cycle->id)>{{ $cycle->code }} · {{ $cycle->label }}</option>
                            @endforeach
                        </select>
                    </label>
                    @error('distribution_cycle_id')<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>

                <x-ui.inline-field label="Date Distributed" name="date_distributed" type="date" max="{{ now()->toDateString() }}"
                                   :value="old('date_distributed', $record?->date_distributed?->toDateString())" :error="$errors->first('date_distributed')"
                                   x-bind:required="status === 'distributed'" />
                <div>
                    <label @class([$box, 'border-field' => ! $errors->has('distribution_status'), 'border-bad' => $errors->has('distribution_status')])>
                        <span class="shrink-0">Distribution Status:</span>
                        <select name="distribution_status" x-model="status" required class="{{ $control }} cursor-pointer" :class="status === '' && 'text-muted'">
                            <option value="" disabled>Distributed / Not Yet Distributed</option>
                            <option value="distributed">Distributed</option>
                            <option value="not_distributed">Not Yet Distributed</option>
                        </select>
                    </label>
                    @error('distribution_status')<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
            </div>

            <button type="submit" class="bg-brand-bar gradient-button mt-[10px] h-[47px] w-full rounded-[50px] text-[20px] font-bold leading-[24px] text-white">Save Intervention Record</button>
        </form>
    </section>
</x-layouts.app>
