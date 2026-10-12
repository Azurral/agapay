@php
    $cols = 'grid-cols-[230px_150px_140px_1fr_260px_155px]';
@endphp
<x-layouts.app title="ELIGIBILITY VERIFICATION">
    <x-intervention.flash />

    <x-ui.card title="Eligibility Verification">
        @if ($passedCount > 0)
            <x-slot:actions>
                <form method="POST" action="{{ route('verification.confirm-passed') }}">
                    @csrf
                    <button type="submit" class="bg-brand-bar gradient-button h-[39px] rounded-[50px] px-[22px] text-[15px] font-bold text-white">
                        Confirm all that passed ({{ $passedCount }})
                    </button>
                </form>
            </x-slot:actions>
        @endif

        <p class="mt-[3px] text-[14px] font-medium leading-[21px] text-muted">
            Automatic checks run on every new program record. Records that pass every check can be confirmed together;
            a flagged record needs a person to decide: choose Eligible, or tag it (OFW, Bedridden, Deceased, Inactive, Relocated, Duplicate).
        </p>

        <div class="mt-[14px] grid {{ $cols }} pl-[16.5px] text-[14px] font-medium leading-[20px] text-muted" aria-hidden="true">
            <span>Name</span><span>RSBSA Number</span><span>Barangay</span><span>Intervention</span><span>Automatic checks</span><span class="text-center">Status</span>
        </div>
        <div class="divider mt-[13px]"></div>

        <div class="mt-[3px]">
            @forelse ($records as $record)
                @php($results = $checks[$record->id])
                @php($flagged = collect($results)->reject(fn ($c) => $c['ok']))
                <div x-data="{ open: {{ $flagged->isNotEmpty() ? 'true' : 'false' }} }" class="border-b border-black/5 py-[4px]">
                    <div class="grid min-h-[45px] {{ $cols }} items-center pl-[16.5px] text-[14px] font-bold leading-[21px]">
                        <a href="{{ route('beneficiaries.show', $record->beneficiary) }}" class="truncate pr-[12px] hover:text-brand hover:underline">{{ $record->beneficiary->fullName() }}</a>
                        <span>{{ $record->beneficiary->rsbsaDisplay() }}</span>
                        <span class="truncate pr-[12px]">{{ $record->beneficiary->barangay?->name }}</span>
                        <span class="truncate pr-[12px]">{{ $record->deLabel() }}</span>
                        <button type="button" @click="open = ! open" :aria-expanded="open.toString()"
                                class="hover-tint mr-[12px] flex h-[32px] items-center gap-[8px] rounded-[10px] px-[10px] text-left text-[13px]">
                            @if ($flagged->isEmpty())
                                <span class="check-pass" aria-hidden="true">✓</span><span>All {{ count($results) }} checks passed</span>
                            @else
                                <span class="check-flag" aria-hidden="true">!</span><span class="text-danger">{{ $flagged->count() }} {{ \Illuminate\Support\Str::plural('flag', $flagged->count()) }} to review</span>
                            @endif
                        </button>
                        <x-intervention.chip-select name="validation_status" :options="\App\Models\InterventionRecord::VALIDATIONS" :selected="$record->validation_status"
                                                    tone="bad" :action="route('intervention-records.validate', $record)" />
                    </div>
                    <ul x-cloak x-show="open" class="mt-[2px] mb-[8px] ml-[16.5px] grid grid-cols-2 gap-x-[24px] gap-y-[4px] rounded-[10px] bg-[#f7f7f9] px-[14px] py-[10px] text-[13px]">
                        @foreach ($results as $check)
                            <li class="flex items-start gap-[8px]">
                                <span class="{{ $check['ok'] ? 'check-pass' : 'check-flag' }} mt-[1px]" aria-hidden="true">{{ $check['ok'] ? '✓' : '!' }}</span>
                                <span><span class="font-bold">{{ $check['label'] }}</span> <span class="text-muted">{{ $check['detail'] }}</span>
                                    <span class="sr-only">{{ $check['ok'] ? '(passed)' : '(flagged)' }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="py-[14px] pl-[16.5px] text-[14px] font-bold">No records are waiting for verification.</p>
            @endforelse
        </div>

        @if ($records->hasPages())
            <div class="mt-[12px] text-[14px]">{{ $records->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
