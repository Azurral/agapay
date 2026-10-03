@php
    $isEdit = $variant === 'edit';
    $barangayName = $beneficiary->barangay?->name;
    $address = $barangayName && ! str_contains(mb_strtolower($beneficiary->address), mb_strtolower($barangayName))
        ? "{$beneficiary->address}, Barangay {$barangayName}"
        : $beneficiary->address;
    $bar = match ($variant) {
        'edit' => ['EDIT MODE', 'Editing beneficiary profile - remember to save your changes.', 'Save Changes'],
        'eligibility' => ['ELIGIBILITY VERIFICATION', 'Checking eligibility status for current intervention cycle...', 'Verify Eligibility'],
        default => ['CLAIM VERIFICATION', 'Checking household claim status for current intervention cycle...', 'Process Claim'],
    };
    $field = 'h-[28px] w-full rounded-[10px] bg-brand-soft/10 px-[6px] text-[14px] font-medium text-brand-soft outline-none focus:ring-2 focus:ring-brand-soft/40';
    $editFields = [
        ['first_name', 'First Name', 'text'], ['middle_name', 'Middle Name', 'text'], ['last_name', 'Last Name', 'text'],
        ['birthdate', 'Birthdate', 'date'], ['age', 'Age', 'computed'], ['address', 'Address', 'text'], ['barangay_id', 'Barangay', 'select'],
        ['rsbsa_number', 'RSBSA Number', 'text'], ['contact_number', 'Contact Number', 'text'],
        ['farm_location', 'Farm Location', 'text'], ['crop_type', 'Crop Type', 'text'], ['household', 'Household Number', 'computed'],
    ];
@endphp
<x-layouts.app title="BENEFICIARY PROFILE">

    {{-- Figma 329:3398 household banner --}}
    <x-ui.card title="Beneficiary Profile" class="pb-[14px]">
        <p @class([
            'mt-[13px] flex min-h-[59px] items-center rounded-[15px] border-4 px-[22px] py-[8px] text-[20px] font-bold leading-[26px]',
            'border-bad' => $others->isNotEmpty(),
            'border-ok' => $others->isEmpty(),
        ])>
            @if ($others->isNotEmpty())
                {{ $others->count() }} other registered {{ $others->count() === 1 ? 'member shares' : 'members share' }} this address ({{ $others->map->fullName()->join(', ') }}) - {{ $householdClaim ? "{$householdClaim->beneficiary->fullName()} already claimed {$householdClaim->intervention->name} this cycle." : 'no other claims made yet.' }}
            @else
                No other registered members share this address.
            @endif
        </p>
    </x-ui.card>

    <x-intervention.flash />

    <div class="mt-[7px] grid grid-cols-[338px_1fr] items-start gap-[21px]">
        <x-ui.card title="Profile Information" class="pb-[22px]">
            @if ($isEdit)
                {{-- The edit form wraps only this card (Save Changes points at it with form=) so the history card's claim dropdowns are not nested forms. --}}
                <form id="profile-edit" method="POST" action="{{ route('beneficiaries.update', $beneficiary) }}" class="mt-[14px] flex flex-col"
                      x-data="{ birthdate: @js(old('birthdate', $beneficiary->birthdate->toDateString())), get age() { if (! this.birthdate) return ''; const b = new Date(this.birthdate), n = new Date(); let a = n.getFullYear() - b.getFullYear(); if (n.getMonth() < b.getMonth() || (n.getMonth() === b.getMonth() && n.getDate() < b.getDate())) a--; return a >= 0 ? a : ''; } }">
                    @csrf
                    @method('PUT')
                    @foreach ($editFields as [$name, $label, $type])
                        <label class="flex flex-col text-[14px] font-medium leading-[18px]">
                            {{ $label }}
                            @if ($name === 'age')
                                <input type="text" readonly tabindex="-1" :value="age" class="{{ $field }}">
                            @elseif ($name === 'household')
                                <input type="text" readonly tabindex="-1" value="{{ $beneficiary->household?->household_no ?? '(auto-grouped)' }}" class="{{ $field }} !text-muted">
                            @elseif ($type === 'select')
                                <span class="relative">
                                    <select name="barangay_id" required class="{{ $field }} cursor-pointer appearance-none">
                                        @foreach ($barangays as $barangay)
                                            <option value="{{ $barangay->id }}" @selected((string) old('barangay_id', $beneficiary->barangay_id) === (string) $barangay->id)>{{ $barangay->name }}</option>
                                        @endforeach
                                    </select>
                                    <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="pointer-events-none absolute top-[6px] right-[8px] size-[16px]">
                                </span>
                            @elseif ($type === 'date')
                                <input type="date" name="birthdate" value="{{ old('birthdate', $beneficiary->birthdate->toDateString()) }}" x-model="birthdate" required
                                       max="{{ now()->subYears(18)->toDateString() }}" @class([$field, 'ring-2 ring-bad' => $errors->has('birthdate')])>
                            @else
                                <input type="text" name="{{ $name }}" value="{{ old($name, $beneficiary->{$name}) }}"
                                       @required(in_array($name, ['first_name', 'last_name', 'address'], true))
                                       @class([$field, 'ring-2 ring-bad' => $errors->has($name)])>
                            @endif
                            @error($name)<span class="text-[12px] font-semibold text-danger">{{ $message }}</span>@enderror
                        </label>
                    @endforeach
                </form>
            @else
                <dl class="mt-[16px] flex flex-col gap-[9px] text-[14px] leading-[18px]">
                    @foreach ([
                        'Full Name' => $beneficiary->fullName(), 'Age' => $beneficiary->age(), 'Address' => $address,
                        'Barangay' => $barangayName, 'RSBSA Number' => $beneficiary->rsbsaDisplay(),
                        'Household Number' => $beneficiary->household?->household_no ?? '(auto-grouped)',
                    ] as $label => $value)
                        <div>
                            <dt class="font-medium text-muted">{{ $label }}</dt>
                            <dd class="font-bold">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </x-ui.card>

        <x-ui.card title="Intervention History" class="self-stretch">
            <div class="mt-[16px] grid grid-cols-[262px_236px_248px_1fr_155px] text-[14px] font-medium leading-[18px] text-muted" aria-hidden="true">
                <span>Date</span><span>Source</span><span>Intervention</span><span>Barangay</span><span class="text-center">Status</span>
            </div>
            <div class="divider mt-[16px]"></div>
            <div class="mt-[3px]">
                @forelse ($records as $record)
                    <div class="grid h-[45px] grid-cols-[262px_236px_248px_1fr_155px] items-center text-[14px] font-bold leading-[18px]">
                        <span>{{ ($record->date_distributed ?? $record->cycle->schedule_date)?->format('M Y') ?? $record->cycle->code }}</span>
                        <span>{{ $record->intervention->sourceLabel() }}</span>
                        <span class="truncate pr-[12px]">{{ $record->intervention->name }}</span>
                        <span>{{ $barangayName }}</span>
                        @if ($isEdit && auth()->user()->can('interventions.claim'))
                            <x-intervention.chip-select name="claim_status" :options="['unclaimed' => 'Unclaimed', 'claimed' => 'Claimed']" :selected="$record->claim_status"
                                                        :tone="$record->claimTone()"
                                                        :actions="['claimed' => route('intervention-records.claim', $record), 'unclaimed' => route('intervention-records.unclaim', $record)]" />
                        @else
                            <x-ui.status-chip :tone="$record->claimTone()">{{ $record->claimLabel() }}</x-ui.status-chip>
                        @endif
                    </div>
                @empty
                    <p class="py-[14px] text-[13px] font-bold">No interventions recorded yet.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>

    @php
        $failed = session('intervention_failed');
        $failedHere = $failed && $records->contains('id', $failed);
        $barButton = 'h-[39px] w-[253px] rounded-[50px] bg-white text-[20px] font-bold text-ink';
    @endphp
    <x-beneficiary.action-bar :title="$bar[0]" :subtitle="$bar[1]" class="mt-[7px]">
        @if ($isEdit)
            <button type="submit" form="profile-edit" class="hover-tint {{ $barButton }} hover:bg-[#efeaff] hover:text-brand">{{ $bar[2] }}</button>
        @elseif ($variant === 'eligibility')
            @if ($records->isNotEmpty())
                <button type="button" x-data @click="$dispatch('open-modal', 'verify-eligibility')" class="{{ $barButton }} transition-colors hover:bg-[#4b32c3] hover:text-white">{{ $bar[2] }}</button>
            @else
                <button type="button" disabled title="No intervention records to verify." class="{{ $barButton }} cursor-not-allowed opacity-80">{{ $bar[2] }}</button>
            @endif
        @else
            @if ($claimable->isNotEmpty() && auth()->user()->can('interventions.claim'))
                <button type="button" x-data @click="$dispatch('open-modal', 'process-claim')" class="{{ $barButton }} transition-colors hover:bg-[#4b32c3] hover:text-white">{{ $bar[2] }}</button>
            @else
                <button type="button" disabled title="No claimable interventions." class="{{ $barButton }} cursor-not-allowed opacity-80">{{ $bar[2] }}</button>
            @endif
        @endif
    </x-beneficiary.action-bar>

    @if ($variant === 'claim' && $claimable->isNotEmpty())
        @php($picked = (string) ($failedHere && $claimable->contains('id', $failed) ? $failed : $claimable->first()->id))
        <x-ui.modal name="process-claim" :title="'Process Claim - '.$beneficiary->fullName()" :open="$failedHere" width="620">
            <form method="POST" :action="urls[picked]" class="flex flex-col gap-[14px]"
                  x-data="{ picked: @js($picked), urls: @js($claimable->mapWithKeys(fn ($r) => [$r->id => route('intervention-records.claim', $r)])), statuses: @js($claimable->pluck('validation_status', 'id')) }">
                @csrf
                <label class="flex h-[44px] items-center rounded-[10px] border border-field bg-white px-[16px] text-[14px] font-bold">
                    <span class="shrink-0">Intervention:</span>
                    <select x-model="picked" class="ml-[6px] h-full min-w-0 flex-1 cursor-pointer bg-transparent font-bold outline-none">
                        @foreach ($claimable as $record)
                            <option value="{{ $record->id }}">{{ $record->intervention->name }} ({{ $record->cycle->code }}){{ $record->validation_status === 'deceased' ? ' · Deceased' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <x-ui.inline-field label="Date Distributed" name="date_distributed" type="date" max="{{ now()->toDateString() }}"
                                   :value="old('date_distributed', now()->toDateString())" :error="$errors->intervention->first('date_distributed')" />
                <div x-show="statuses[picked] === 'deceased'" class="flex flex-col gap-[14px]">
                    <x-ui.inline-field label="Proxy Claimant" name="proxy_claimant" placeholder="Full name of the family member claiming" :value="old('proxy_claimant')" />
                    <x-ui.inline-field label="Proof Note" name="proof_note" placeholder="e.g. Death certificate No., barangay certification" :value="old('proof_note')" />
                </div>
                @if ($others->isNotEmpty())
                    <x-ui.inline-field label="Override reason (household already claimed)" name="override_reason" placeholder="Administrator only; leave blank normally" :value="old('override_reason')" />
                @endif
                <button type="submit" class="bg-brand-bar gradient-button h-[44px] rounded-[50px] text-[16px] font-bold text-white">Confirm Claim</button>
            </form>
        </x-ui.modal>
    @endif

    @if ($variant === 'eligibility' && $records->isNotEmpty())
        {{-- Figma 337:18 "Validate Beneficiary" modal; one status per record, so the options are single-choice. --}}
        @php($first = $records->firstWhere('validation_status', 'pending') ?? $records->first())
        @php($picked = (string) ($failedHere ? $failed : $first->id))
        <x-ui.modal name="verify-eligibility" :title="'Validate Beneficiary: '.$beneficiary->fullName()" :open="$failedHere" width="560">
            <form method="POST" :action="urls[picked]" class="-mt-[18px] flex flex-col"
                  x-data="{ picked: @js($picked), urls: @js($records->mapWithKeys(fn ($r) => [$r->id => route('intervention-records.validate', $r)])), statuses: @js($records->pluck('validation_status', 'id')), status: '' }"
                  x-init="status = statuses[picked]; $watch('picked', v => status = statuses[v])">
                @csrf
                <p class="text-[14px] font-medium leading-[20px] text-muted">{{ $beneficiary->rsbsaDisplay() }} - Check all that apply, per DA/barangay cross-check</p>
                @if ($records->count() > 1)
                    <label class="mt-[14px] flex h-[40px] items-center rounded-[10px] border border-field bg-white px-[14px] text-[14px] font-bold">
                        <span class="shrink-0">Record:</span>
                        <select x-model="picked" class="ml-[6px] h-full min-w-0 flex-1 cursor-pointer bg-transparent font-bold outline-none">
                            @foreach ($records as $record)
                                <option value="{{ $record->id }}">{{ $record->deLabel() }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <div class="mt-[18px] flex flex-col gap-[18px]">
                    @foreach (\App\Models\InterventionRecord::VALIDATIONS as $value => $label)
                        <label class="flex cursor-pointer items-center gap-[12px] text-[16px] font-medium leading-[20px]">
                            <input type="radio" name="validation_status" value="{{ $value }}" x-model="status" required class="size-[22px] cursor-pointer accent-brand">
                            {{ $value === 'pending' ? 'Not yet validated' : ($value === 'ofw' ? 'OFW (Overseas Filipino Worker)' : $label) }}
                        </label>
                    @endforeach
                </div>
                @error('validation_status', 'intervention')<p class="mt-[8px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
                <button type="submit" class="bg-brand-bar gradient-button mt-[24px] h-[44px] rounded-[50px] text-[16px] font-bold text-white">Confirm Validation</button>
                <button type="button" @click="open = false" class="mt-[10px] text-center text-[14px] font-medium text-muted hover:text-brand">Cancel</button>
            </form>
        </x-ui.modal>
    @endif
</x-layouts.app>
