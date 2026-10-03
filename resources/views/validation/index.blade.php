<x-layouts.app title="BENEFICIARY VALIDATION">
    <x-intervention.flash />

    <x-ui.card title="Beneficiary Validation">
        <p class="mt-[3px] text-[14px] font-medium leading-[21px] text-muted">Intervention records waiting for an eligibility check (DA/barangay cross-check).</p>
        <div class="mt-[14px] grid grid-cols-[251px_200px_200px_1fr_155px] pl-[16.5px] text-[14px] font-medium leading-[20px] text-muted" aria-hidden="true">
            <span>Name</span><span>RSBSA Number</span><span>Barangay</span><span>Intervention</span><span class="text-center">Validation</span>
        </div>
        <div class="divider mt-[13px]"></div>

        <div class="mt-[3px]">
            @forelse ($records as $record)
                <div class="grid h-[45px] grid-cols-[251px_200px_200px_1fr_155px] items-center pl-[16.5px] text-[14px] font-bold leading-[21px]">
                    <a href="{{ route('beneficiaries.show', $record->beneficiary) }}" class="truncate pr-[12px] hover:text-brand hover:underline">{{ $record->beneficiary->fullName() }}</a>
                    <span>{{ $record->beneficiary->rsbsaDisplay() }}</span>
                    <span>{{ $record->beneficiary->barangay?->name }}</span>
                    <span class="truncate pr-[12px]">{{ $record->deLabel() }}</span>
                    <x-intervention.chip-select name="validation_status" :options="\App\Models\InterventionRecord::VALIDATIONS" :selected="$record->validation_status"
                                                tone="bad" :action="route('intervention-records.validate', $record)" />
                </div>
            @empty
                <p class="py-[14px] pl-[16.5px] text-[14px] font-bold">No records are waiting for validation.</p>
            @endforelse
        </div>

        @if ($records->hasPages())
            <div class="mt-[12px] text-[14px]">{{ $records->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
