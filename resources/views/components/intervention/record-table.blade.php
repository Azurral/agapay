@props(['records', 'source', 'variant', 'interventions', 'barangays'])
@php
    use App\Models\InterventionRecord;

    // Figma 344:76 column widths, measured from the card's inner edge (DA and LGU program records alike).
    $cols = 'grid-cols-[251px_260px_255px_242px_100px_162px_155px]';
    $filterWidth = 230;
    $claimOptions = [InterventionRecord::CLAIM_UNCLAIMED => 'Unclaimed', InterventionRecord::CLAIM_CLAIMED => 'Claimed'];
    $canValidate = $variant === 'dropdowns' && auth()->user()->can('interventions.validate');
    $canClaim = $variant === 'dropdowns' && auth()->user()->can('interventions.claim');
@endphp

<form method="GET" class="relative mt-[20.5px] flex h-[39px] items-center pl-[1.5px]" style="gap: 25px">
    @if ($source === 'lgu')
        <input type="hidden" name="tab" value="records">
    @endif
    <x-ui.pill-input name="name" placeholder="Search Name..." :value="request()->queryText('name')" :width="$filterWidth" />
    <x-ui.pill-input name="rsbsa" placeholder="RSBSA Number..." :value="request()->queryText('rsbsa')" :width="$filterWidth" />
    <x-ui.pill-select name="barangay" label="Barangay" :options="['' => 'All'] + $barangays->all()" :selected="request()->queryText('barangay')" :width="$filterWidth" />
    <x-ui.pill-select name="intervention" label="Intervention" :options="['' => 'All', 'goods' => 'All goods', 'cash' => 'All cash'] + $interventions->all()" :selected="request()->queryText('intervention')" :width="$filterWidth" />
    <span class="absolute left-[1024.5px] text-[14px] font-medium text-muted">Qty / Unit</span>
    {{-- Not claimable records (OFW, Bedridden, Deceased, Inactive, Relocated, Duplicate) leave the list by themselves. --}}
    <select name="show" onchange="this.form.submit()" aria-label="Validation"
            class="absolute left-[1124.5px] w-[155px] cursor-pointer bg-transparent text-center text-[14px] font-medium text-muted outline-none">
        @foreach (['' => 'Validation: Claimable', 'not' => 'Not claimable', 'all' => 'All'] as $value => $text)
            <option value="{{ $value }}" @selected(request()->queryText('show') === $value)>{{ $text }}</option>
        @endforeach
    </select>
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
            <span class="truncate pr-[12px]">{{ $record->quantityDisplay() }}</span>
            <span>
                @if ($record->pendingProof)
                    {{-- A change sent with proof waits for the Administrator; no second change meanwhile. --}}
                    <x-ui.status-chip tone="neutral" pulse title="Sent with proof, waiting for the Administrator">Pending: {{ InterventionRecord::validationLabel($record->pendingProof->to_status) }}</x-ui.status-chip>
                @elseif ($canValidate)
                    <x-intervention.validation-select :record="$record" />
                @else
                    <x-ui.status-chip :tone="InterventionRecord::validationTone($record->validation_status)">{{ InterventionRecord::validationLabel($record->validation_status) }}</x-ui.status-chip>
                @endif
            </span>
            <span>
                @if ($canClaim && ! $record->isClaimLocked())
                    <x-intervention.chip-select name="claim_status" :options="$claimOptions" :selected="$record->claim_status" :tone="$record->claimTone()" :pop="session('changed_record') === $record->id"
                                                :actions="['claimed' => route('intervention-records.claim', $record), 'unclaimed' => route('intervention-records.unclaim', $record)]" />
                @else
                    <x-ui.status-chip :tone="$record->claimTone()" :pop="session('changed_record') === $record->id">{{ $record->claimLabel() }}</x-ui.status-chip>
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
