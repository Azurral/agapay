@php
    use App\Models\DistributionCycle;

    $cols = 'grid-cols-[150px_300px_180px_1fr_140px_110px_90px]';
    $editing = old('editing_id');
    $cycleData = $cycles->map(fn ($c) => [
        'id' => $c->id, 'code' => $c->code, 'label' => $c->label, 'schedule_date' => $c->schedule_date?->toDateString(),
        'venue' => $c->venue, 'status' => $c->status, 'url' => route('cycles.update', $c),
    ])->values();
    $oldForm = ['id' => $editing ? (int) $editing : null, 'code' => old('code', ''), 'label' => old('label', ''),
        'schedule_date' => old('schedule_date', ''), 'venue' => old('venue', ''), 'status' => old('status', DistributionCycle::STATUS_SCHEDULED)];
@endphp
<x-layouts.app title="DISTRIBUTION CYCLES">
    {{-- No Figma frame: reuses the card, table and modal components (spec §2). --}}
    <x-intervention.flash />

    <div x-data="{
            cycles: @js($cycleData), form: @js($oldForm), storeUrl: @js(route('cycles.store')),
            blank() { return { id: null, code: '', label: '', schedule_date: '', venue: '', status: 'scheduled' }; },
            add() { this.form = this.blank(); $dispatch('open-modal', 'cycle-form'); },
            edit(cycle) { this.form = { ...cycle }; $dispatch('open-modal', 'cycle-form'); },
        }">
        <x-ui.card title="Distribution Cycles">
            <x-slot:actions>
                <button type="button" @click="add()" class="flex h-[39px] w-[200px] items-center justify-center rounded-[8px] bg-brand-soft text-[14px] font-bold text-white transition-colors hover:bg-[#4b32c3]">+ Add Cycle</button>
            </x-slot:actions>
            <p class="mt-[6px] text-[14px] font-medium leading-[18px] text-muted">Distributions and reports belong to a cycle. Only one cycle is ongoing at a time; starting one completes the previous.</p>

            <div class="mt-[18px] grid {{ $cols }} text-[14px] font-medium leading-[20px] text-muted" aria-hidden="true">
                <span>Code</span><span>Label</span><span>Schedule Date</span><span>Venue</span><span>Status</span><span>Records</span><span></span>
            </div>
            <div class="divider mt-[13px]"></div>
            <div role="list">
                @forelse ($cycles as $cycle)
                    <div role="listitem" class="grid min-h-[45px] {{ $cols }} items-center text-[14px] font-bold leading-[20px]">
                        <span>{{ $cycle->code }}</span>
                        <span class="truncate pr-[12px]">{{ $cycle->label }}</span>
                        <span>{{ $cycle->schedule_date?->format('M j, Y') ?? '—' }}</span>
                        <span class="truncate pr-[12px]">{{ $cycle->venue ?: '—' }}</span>
                        <span>
                            <span @class(['inline-flex h-[28px] items-center rounded-[8px] border-2 bg-white px-[10px] text-[12px]',
                                'border-ok' => $cycle->status === DistributionCycle::STATUS_ONGOING,
                                'border-field' => $cycle->status !== DistributionCycle::STATUS_ONGOING])>{{ $cycle->statusLabel() }}</span>
                        </span>
                        <span>{{ $cycle->records_count }}</span>
                        <button type="button" @click="edit(cycles.find(c => c.id === {{ $cycle->id }}))" class="justify-self-end text-brand hover:underline">Edit</button>
                    </div>
                @empty
                    <p class="py-[14px] text-[14px] font-bold">No distribution cycles yet.</p>
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.modal name="cycle-form" title="Distribution Cycle" :open="$errors->any()" width="700">
            <form method="POST" :action="form.id ? cycles.find(c => c.id === form.id)?.url : storeUrl" class="flex flex-col gap-[12px]">
                @csrf
                <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                <input type="hidden" name="editing_id" :value="form.id ?? ''">
                <div class="grid grid-cols-2 gap-[12px]">
                    <x-ui.inline-field label="Code" name="code" maxlength="20" placeholder="e.g. 2026-Q4" required x-model="form.code" :error="$errors->first('code')" />
                    <x-ui.inline-field label="Schedule Date" name="schedule_date" type="date" required x-model="form.schedule_date" :error="$errors->first('schedule_date')" />
                    <div class="col-span-2">
                        <x-ui.inline-field label="Label" name="label" maxlength="100" placeholder="e.g. 2026-Q4 Wet Season" required x-model="form.label" :error="$errors->first('label')" />
                    </div>
                    <x-ui.inline-field label="Venue" name="venue" maxlength="255" placeholder="e.g. Bontoc Municipal Gym" x-model="form.venue" :error="$errors->first('venue')" />
                    <div>
                        <label @class(['flex h-[44px] items-center rounded-[10px] border bg-white px-[16px] text-[14px] font-bold', 'border-field' => ! $errors->has('status'), 'border-bad' => $errors->has('status')])>
                            <span class="shrink-0">Status:</span>
                            <select name="status" x-model="form.status" class="ml-[6px] h-full min-w-0 flex-1 cursor-pointer bg-transparent font-bold outline-none">
                                @foreach (DistributionCycle::STATUSES as $value => $text)
                                    <option value="{{ $value }}">{{ $text }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>
                <button type="submit" class="bg-brand-bar gradient-button mt-[8px] h-[44px] rounded-[50px] text-[18px] font-bold text-white" x-text="form.id ? 'Save Cycle' : 'Add Cycle'">Add Cycle</button>
            </form>
        </x-ui.modal>
    </div>
</x-layouts.app>
