@php
    $cols = 'grid-cols-[1fr_120px_170px_150px_120px_100px_70px]';
    $editing = old('editing_id');
    $programData = collect($groups)->flatMap(fn ($g) => $g[2])->map(fn ($p) => [
        'id' => $p->id, 'source' => $p->source, 'name' => $p->name, 'unit' => $p->unit,
        'one_per_household' => $p->one_per_household, 'allow_repeat' => $p->allow_repeat, 'is_active' => $p->is_active,
        'url' => route('programs.update', $p),
    ])->values();
    $oldForm = ['id' => $editing ? (int) $editing : null, 'source' => old('source', 'lgu'), 'name' => old('name', ''), 'unit' => old('unit', ''),
        'one_per_household' => (bool) old('one_per_household'), 'allow_repeat' => (bool) old('allow_repeat'), 'is_active' => (bool) old('is_active', true)];
@endphp
<x-layouts.app title="PROGRAMS">
    {{-- No Figma frame: reuses the card, list and modal components of Distribution Cycles. --}}
    <x-intervention.flash />

    <div class="flex flex-col gap-[14px]" x-data="{
            programs: @js($programData), form: @js($oldForm), storeUrl: @js(route('programs.store')),
            add(source) { this.form = { id: null, source, name: '', unit: '', one_per_household: false, allow_repeat: false, is_active: true }; $dispatch('open-modal', 'program-form'); },
            edit(id) { this.form = { ...this.programs.find(p => p.id === id) }; $dispatch('open-modal', 'program-form'); },
        }">
        @foreach ($groups as $source => [$title, $hint, $programs])
            <x-ui.card :title="$title">
                <x-slot:actions>
                    <button type="button" @click="add('{{ $source }}')" class="flex h-[39px] w-[200px] items-center justify-center rounded-[8px] bg-brand-soft text-[14px] font-bold text-white transition-colors hover:bg-[#4b32c3]">+ Add {{ strtoupper($source) }} Program</button>
                </x-slot:actions>
                <p class="mt-[6px] text-[14px] font-medium leading-[18px] text-muted">{{ $hint }}</p>

                <div class="mt-[16px] grid {{ $cols }} text-[14px] font-medium leading-[20px] text-muted" aria-hidden="true">
                    <span>Program</span><span>Unit</span><span>One per household</span><span>Repeats allowed</span><span>Status</span><span>Records</span><span></span>
                </div>
                <div class="divider mt-[11px]"></div>
                <div role="list">
                    @forelse ($programs as $program)
                        <div role="listitem" class="grid min-h-[44px] {{ $cols }} items-center text-[14px] font-bold leading-[20px]">
                            <span class="truncate pr-[12px] {{ $program->is_active ? '' : 'text-muted' }}">{{ $program->name }}</span>
                            <span>{{ $program->unit ?: '—' }}</span>
                            <span>{{ $program->one_per_household ? 'Yes' : 'No' }}</span>
                            <span>{{ $program->allow_repeat ? 'Yes' : 'No' }}</span>
                            <span>
                                <span @class(['inline-flex h-[28px] items-center rounded-[8px] border-2 bg-white px-[10px] text-[12px]',
                                    'border-ok' => $program->is_active, 'border-field text-muted' => ! $program->is_active])>{{ $program->is_active ? 'Active' : 'Inactive' }}</span>
                            </span>
                            <span>{{ $program->records_count }}</span>
                            <button type="button" @click="edit({{ $program->id }})" class="justify-self-end text-brand hover:underline">Edit</button>
                        </div>
                    @empty
                        <p class="py-[14px] text-[14px] font-bold">No {{ strtoupper($source) }} programs yet.</p>
                    @endforelse
                </div>
            </x-ui.card>
        @endforeach

        <x-ui.modal name="program-form" title="Program" :open="$errors->any()" width="700">
            <form method="POST" :action="form.id ? programs.find(p => p.id === form.id)?.url : storeUrl" class="flex flex-col gap-[12px]">
                @csrf
                <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                <input type="hidden" name="editing_id" :value="form.id ?? ''">
                <div class="grid grid-cols-[200px_1fr] gap-[12px]">
                    <label @class(['flex h-[44px] items-center rounded-[10px] border bg-white px-[16px] text-[14px] font-bold', 'border-field' => ! $errors->has('source'), 'border-bad' => $errors->has('source')])>
                        <span class="shrink-0">Source:</span>
                        <select name="source" x-model="form.source" class="ml-[6px] h-full min-w-0 flex-1 cursor-pointer bg-transparent font-bold outline-none">
                            <option value="da">DA</option>
                            <option value="lgu">LGU</option>
                        </select>
                    </label>
                    <x-ui.inline-field label="Program" name="name" maxlength="100" placeholder="e.g. Heirloom Rice Seeds" required x-model="form.name" :error="$errors->first('name')" />
                    <x-ui.inline-field label="Unit" name="unit" maxlength="20" placeholder="e.g. sack, kg, L (optional)" x-model="form.unit" :error="$errors->first('unit')" />
                    <div class="flex flex-col justify-center gap-[6px] pl-[4px] text-[14px] font-bold">
                        <label class="flex cursor-pointer items-center gap-[8px]"><input type="hidden" name="one_per_household" value="0"><input type="checkbox" name="one_per_household" value="1" x-model="form.one_per_household" class="size-[16px] accent-brand"> One per household per cycle</label>
                        <label class="flex cursor-pointer items-center gap-[8px]"><input type="hidden" name="allow_repeat" value="0"><input type="checkbox" name="allow_repeat" value="1" x-model="form.allow_repeat" class="size-[16px] accent-brand"> May be given again in later cycles</label>
                        <label class="flex cursor-pointer items-center gap-[8px]"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="size-[16px] accent-brand"> Active (offered for new records)</label>
                    </div>
                </div>
                <button type="submit" class="bg-brand-bar gradient-button mt-[8px] h-[44px] rounded-[50px] text-[18px] font-bold text-white" x-text="form.id ? 'Save Program' : 'Add Program'">Add Program</button>
            </form>
        </x-ui.modal>
    </div>
</x-layouts.app>
