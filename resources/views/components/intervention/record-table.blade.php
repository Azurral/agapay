@props(['records', 'source', 'variant', 'interventions', 'barangays'])
@php
    use App\Models\InterventionRecord;

    $lgu = $source === 'lgu';
    // Figma 344:76 (DA) / 329:1423 (LGU) column widths, measured from the card's inner edge.
    $cols = $lgu ? 'grid-cols-[220px_231px_225px_223px_209px_162px_155px]' : 'grid-cols-[251px_260px_255px_242px_100px_162px_155px]';
    $filterWidth = $lgu ? 199 : 230;
    $validationOptions = InterventionRecord::VALIDATIONS;
    $claimOptions = [InterventionRecord::CLAIM_UNCLAIMED => 'Unclaimed', InterventionRecord::CLAIM_CLAIMED => 'Claimed'];
    $canValidate = $variant === 'dropdowns' && auth()->user()->can('interventions.validate');
    $canClaim = $variant === 'dropdowns' && auth()->user()->can('interventions.claim');
@endphp

<form method="GET" class="relative mt-[20.5px] flex h-[39px] items-center pl-[1.5px]" style="gap: {{ $lgu ? 24.5 : 25 }}px">
    <x-ui.pill-input name="name" placeholder="Search Name..." :value="request()->queryText('name')" :width="$filterWidth" />
    <x-ui.pill-input name="rsbsa" placeholder="RSBSA Number..." :value="request()->queryText('rsbsa')" :width="$filterWidth" />
    <x-ui.pill-select name="barangay" label="Barangay" :options="['' => 'All'] + $barangays->all()" :selected="request()->queryText('barangay')" :width="$filterWidth" />
    <x-ui.pill-select name="intervention" label="Intervention" :options="['' => 'All'] + $interventions->all()" :selected="request()->queryText('intervention')" :width="$filterWidth" />
    @if ($lgu)
        <x-ui.pill-select name="registration" label="Registration" :options="['' => 'All', 'registered' => 'Registered', 'new' => 'New', 'unregistered' => 'Unregistered']" :selected="request()->queryText('registration')" :width="$filterWidth" />
    @else
        <span class="absolute left-[1024.5px] text-[14px] font-medium text-muted">Qty / Unit</span>
    @endif
    <span class="absolute left-[1124.5px] w-[155px] text-center text-[14px] font-medium text-muted">Validation</span>
    <span class="absolute left-[1286.5px] w-[155px] text-center text-[14px] font-medium text-muted">Status</span>
    <button type="submit" class="sr-only">Apply filters</button>
</form>

<div class="divider mt-[15px]"></div>

<div class="mt-[3px]">
    @forelse ($records as $record)
        @php($beneficiary = $record->beneficiary)
        <div class="grid h-[45px] {{ $cols }} items-center pl-[16.5px] text-[14px] font-bold leading-[21px]">
            <a href="{{ route('beneficiaries.show', $beneficiary) }}" class="truncate pr-[12px] hover:text-brand hover:underline">{{ $beneficiary->fullName() }}</a>
            <span class="truncate pr-[12px]">{{ $beneficiary->rsbsaDisplay() }}</span>
            <span class="truncate pr-[12px]">{{ $beneficiary->barangay?->name }}</span>
            <span class="truncate pr-[12px]">{{ $record->intervention->name }}</span>
            <span class="truncate pr-[12px]">{{ $lgu ? $beneficiary->registrationLabel() : $record->quantityDisplay() }}</span>
            <span>
                @if ($canValidate)
                    <x-intervention.chip-select name="validation_status" :options="$validationOptions" :selected="$record->validation_status"
                                                :tone="InterventionRecord::validationTone($record->validation_status)"
                                                :action="route('intervention-records.validate', $record)" />
                @else
                    <x-ui.status-chip :tone="InterventionRecord::validationTone($record->validation_status)">{{ InterventionRecord::validationLabel($record->validation_status) }}</x-ui.status-chip>
                @endif
            </span>
            <span>
                @if ($canClaim)
                    <x-intervention.chip-select name="claim_status" :options="$claimOptions" :selected="$record->claim_status" :tone="$record->claimTone()"
                                                :actions="['claimed' => route('intervention-records.claim', $record), 'unclaimed' => route('intervention-records.unclaim', $record)]" />
                @else
                    <x-ui.status-chip :tone="$record->claimTone()">{{ $record->claimLabel() }}</x-ui.status-chip>
                @endif
            </span>
        </div>
    @empty
        <p class="py-[14px] pl-[16.5px] text-[14px] font-bold">No intervention records match these filters.</p>
    @endforelse
</div>

@if ($records->hasPages())
    <div class="mt-[12px] text-[14px]">{{ $records->links() }}</div>
@endif
