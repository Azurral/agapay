@php
    $box = 'flex h-[44px] items-center rounded-[10px] border bg-white px-[16px] text-[14px] font-bold leading-[20px]';
    $control = 'ml-[6px] h-full min-w-0 flex-1 bg-transparent font-bold outline-none placeholder:text-muted';
    $border = fn (string $field) => $errors->has($field) ? 'border-bad' : 'border-field';
    $picked = old('beneficiary_id') ? \App\Models\Beneficiary::find(old('beneficiary_id')) : null;
@endphp
<x-layouts.app title="ASSISTANCE REQUESTS">
    @if (session('status'))
        <p class="flash rounded-[10px] border-[1.5px] border-ok bg-white px-[16px] py-[10px] text-[14px] font-bold" role="status" data-autohide>{{ session('status') }}</p>
    @endif

    <section class="rounded-[20px] border-[1.5px] border-black/10 bg-white pt-[19.5px] pr-[26.5px] pb-[22px] pl-[21.5px]">
        <h2 class="text-[16px] font-bold leading-[20px]">New Assistance Request</h2>
        <p class="mt-[2px] text-[13px] text-muted">A farmer asking OMAG for a program, for example after a crisis. The Administrator approves or denies it.</p>
        <div class="divider mt-[10px]"></div>

        <form method="POST" action="{{ route('assistance-requests.store') }}" class="mt-[14px] flex flex-col gap-[12px]"
              x-data="{ source: @js(old('source', '')), interventions: @js($interventions->map->only(['id', 'source', 'name'])), intervention: @js((string) old('intervention_id', '')) }">
            @csrf

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
                <label class="{{ $box }} {{ $border('beneficiary_id') }}">
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

            <div class="grid grid-cols-2 gap-x-[23px] gap-y-[12px]">
                <div>
                    <label class="{{ $box }} {{ $border('source') }}">
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
                    <label class="{{ $box }} {{ $border('intervention_id') }}">
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

                <x-ui.inline-field label="Quantity" name="quantity" type="number" step="0.01" min="0" placeholder="e.g. 2 (optional)"
                                   :value="old('quantity')" :error="$errors->first('quantity')" />
                <div>
                    <label class="{{ $box }} {{ $border('disaster_id') }}">
                        <span class="shrink-0">Crisis:</span>
                        <select name="disaster_id" class="{{ $control }} cursor-pointer">
                            <option value="">None / not crisis-related</option>
                            @foreach ($disasters as $disaster)
                                <option value="{{ $disaster->id }}" @selected((string) old('disaster_id') === (string) $disaster->id)>{{ $disaster->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @error('disaster_id')<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $box }} {{ $border('crop_id') }}">
                        <span class="shrink-0">Crop:</span>
                        <select name="crop_id" class="{{ $control }} cursor-pointer">
                            <option value="">Farmer's usual crop</option>
                            @foreach ($crops as $crop)
                                <option value="{{ $crop->id }}" @selected((string) old('crop_id') === (string) $crop->id)>{{ $crop->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @error('crop_id')<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
                <x-ui.inline-field label="Reason" name="reason" maxlength="500" placeholder="e.g. Seedbed washed out by the typhoon"
                                   :value="old('reason')" :error="$errors->first('reason')" />
            </div>

            <button type="submit" class="bg-brand-bar gradient-button mt-[10px] h-[47px] w-full rounded-[50px] text-[20px] font-bold leading-[24px] text-white">File Request</button>
        </form>
    </section>

    <x-ui.card title="Your recent requests">
        <div class="mt-[12px] grid grid-cols-[1fr_1fr_160px_160px] pl-[4px] text-[13px] font-medium text-muted" aria-hidden="true">
            <span>Farmer</span><span>Program</span><span>Filed</span><span class="text-center">Status</span>
        </div>
        <div class="divider mt-[8px]"></div>
        @forelse ($recent as $filed)
            <div class="grid h-[45px] grid-cols-[1fr_1fr_160px_160px] items-center pl-[4px] text-[14px] font-bold">
                <span class="truncate pr-[12px]">{{ $filed->beneficiary->fullName() }}</span>
                <span class="truncate pr-[12px]">{{ $filed->intervention->sourcedName() }}</span>
                <span>{{ $filed->created_at->format('M j, Y') }}</span>
                <x-ui.status-chip :tone="$filed->statusTone()" :pulse="$filed->isPending()">{{ $filed->statusLabel() }}</x-ui.status-chip>
            </div>
        @empty
            <p class="py-[14px] pl-[4px] text-[13px] font-bold">You have not filed any requests yet.</p>
        @endforelse
    </x-ui.card>
</x-layouts.app>
