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
    @if (session('status'))
        <p class="rounded-[10px] border-[1.5px] border-ok bg-white px-[16px] py-[10px] text-[14px] font-bold" role="status">{{ session('status') }}</p>
    @endif

    {{-- Figma 329:3398 household banner --}}
    <x-ui.card title="Beneficiary Profile" class="pb-[14px]">
        <p @class([
            'mt-[13px] flex min-h-[59px] items-center rounded-[15px] border-4 px-[22px] py-[8px] text-[20px] font-bold leading-[26px]',
            'border-bad' => $others->isNotEmpty(),
            'border-ok' => $others->isEmpty(),
        ])>
            @if ($others->isNotEmpty())
                {{ $others->count() }} other registered {{ $others->count() === 1 ? 'member shares' : 'members share' }} this address ({{ $others->map->fullName()->join(', ') }}) - no other claims made yet.
            @else
                No other registered members share this address.
            @endif
        </p>
    </x-ui.card>

    @if ($isEdit)
        <form method="POST" action="{{ route('beneficiaries.update', $beneficiary) }}" class="mt-[7px] flex flex-col gap-[21px]"
              x-data="{ birthdate: @js(old('birthdate', $beneficiary->birthdate->toDateString())), get age() { if (! this.birthdate) return ''; const b = new Date(this.birthdate), n = new Date(); let a = n.getFullYear() - b.getFullYear(); if (n.getMonth() < b.getMonth() || (n.getMonth() === b.getMonth() && n.getDate() < b.getDate())) a--; return a >= 0 ? a : ''; } }">
            @csrf
            @method('PUT')
    @endif

    <div @class(['grid grid-cols-[338px_1fr] items-start gap-[21px]', 'mt-[7px]' => ! $isEdit])>
        <x-ui.card title="Profile Information" class="pb-[22px]">
            @if ($isEdit)
                <div class="mt-[14px] flex flex-col">
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
                </div>
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
            {{-- Phase 4 lists DA/LGU intervention records here. --}}
            <p class="py-[14px] text-[13px] font-bold">No interventions recorded yet.</p>
        </x-ui.card>
    </div>

    <x-beneficiary.action-bar :title="$bar[0]" :subtitle="$bar[1]" :class="$isEdit ? '' : 'mt-[7px]'">
        @if ($isEdit)
            <button type="submit" class="hover-tint h-[39px] w-[253px] rounded-[50px] bg-white text-[20px] font-bold text-ink hover:bg-[#efeaff] hover:text-brand">{{ $bar[2] }}</button>
        @else
            <button type="button" disabled title="Available once intervention records exist (Phase 4)."
                    class="h-[39px] w-[253px] cursor-not-allowed rounded-[50px] bg-white text-[20px] font-bold text-ink">{{ $bar[2] }}</button>
        @endif
    </x-beneficiary.action-bar>

    @if ($isEdit)
        </form>
    @endif
</x-layouts.app>
