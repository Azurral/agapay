@php($label = strtoupper($source))
<x-layouts.app :title="$label.' INTERVENTION LIST'">
    {{-- Figma 329:1250 / 329:1423 (Admin) and 407:323 / 407:603 (Agri Tech) --}}
    <x-intervention.flash />

    <x-ui.card :title="$label.' Intervention Beneficiaries'">
        @if ($canArchive)
            <x-slot:actions>
                <div class="-mt-[11.5px] -mr-[18.5px]"><x-intervention.segmented-toggle :source="$source" active="records" /></div>
            </x-slot:actions>
        @endif

        <x-intervention.record-table :records="$records" :source="$source" :variant="$variant" :interventions="$interventions" :barangays="$barangays" />
    </x-ui.card>

    @if ($canArchive)
        <div class="-mt-[2px]">
            <button type="button" x-data @click="$dispatch('open-modal', 'archive-record')" @disabled($records->isEmpty())
                    class="border-gradient pill-button h-[39px] w-[174px] rounded-[50px] text-[20px] font-bold leading-[24px] disabled:cursor-not-allowed disabled:opacity-60">Archive</button>
        </div>
    @endif
</x-layouts.app>
