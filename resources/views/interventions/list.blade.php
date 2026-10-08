@php($label = strtoupper($source))
<x-layouts.app :title="$label.' INTERVENTION LIST'">
    {{-- Figma 329:1250 / 329:1423 (Admin) and 407:323 / 407:603 (Agri Tech) --}}
    <x-intervention.flash />

    <x-ui.card :title="$label.' Intervention Beneficiaries'">
        @if ($canArchive || $source === 'lgu')
            <x-slot:actions>
                <div class="-mt-[11.5px] -mr-[18.5px] flex gap-[12px]">
                    @if ($source === 'lgu')
                        <x-intervention.lgu-tabs active="records" />
                    @endif
                    @if ($canArchive)
                        <x-intervention.segmented-toggle :source="$source" active="records" />
                    @endif
                </div>
            </x-slot:actions>
        @endif

        <x-intervention.record-table :records="$records" :source="$source" :variant="$variant" :interventions="$interventions" :barangays="$barangays" />
    </x-ui.card>

    @if ($canArchive)
        <div class="-mt-[2px]">
            <button type="button" x-data @click="$dispatch('open-modal', 'archive-record')" @disabled($records->isEmpty())
                    class="border-gradient pill-button h-[39px] w-[174px] rounded-[50px] text-[20px] font-bold leading-[24px] disabled:cursor-not-allowed disabled:opacity-60">Archive</button>
        </div>

        @if ($records->isNotEmpty())
            @php($urls = $records->mapWithKeys(fn ($r) => [$r->id => route('intervention-records.archive', $r)]))
            @php($failed = $errors->intervention->has('reason') ? session('intervention_failed') : null)
            <x-ui.modal name="archive-record" title="Archive Intervention Record" :open="(bool) $failed" width="620">
                <form method="POST" :action="urls[picked]" class="flex flex-col gap-[16px]"
                      x-data="{ urls: @js($urls), picked: @js((string) ($failed ?? $records->first()->id)) }">
                    @csrf
                    <label class="flex h-[44px] items-center rounded-[10px] border border-field bg-white px-[16px] text-[14px] font-bold">
                        <span class="shrink-0">Record:</span>
                        <select x-model="picked" class="ml-[6px] h-full min-w-0 flex-1 cursor-pointer bg-transparent font-bold outline-none">
                            @foreach ($records as $record)
                                <option value="{{ $record->id }}">{{ $record->auditRecordLabel() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-ui.inline-field label="Reason" name="reason" placeholder="e.g. Duplicate entry (barangay re-encode)" required
                                       :value="$failed ? old('reason') : null" :error="$errors->intervention->first('reason')" />
                    <p class="text-[13px] font-medium text-muted">Archived records move to Archived/Restore and can be restored later.</p>
                    <button type="submit" class="bg-brand-bar gradient-button h-[44px] rounded-[50px] text-[16px] font-bold text-white">Archive Record</button>
                </form>
            </x-ui.modal>
        @endif
    @endif
</x-layouts.app>
