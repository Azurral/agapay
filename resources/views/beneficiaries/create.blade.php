<x-layouts.app title="ADD BENEFICIARY">
    @if (session('status'))
        <p class="rounded-[10px] border-[1.5px] border-ok bg-white px-[16px] py-[10px] text-[14px] font-bold" role="status">{{ session('status') }}</p>
    @endif

    {{-- Figma 329:3290 form card + 329:3330 submit bar (20px apart). --}}
        <form method="POST" action="{{ route('beneficiaries.store') }}" class="flex flex-col gap-[20px]"
              x-on:submit="busy = true"
              x-data="{ busy: false, birthdate: @js(old('birthdate', '')), get age() { if (! this.birthdate) return ''; const b = new Date(this.birthdate), n = new Date(); let a = n.getFullYear() - b.getFullYear(); if (n.getMonth() < b.getMonth() || (n.getMonth() === b.getMonth() && n.getDate() < b.getDate())) a--; return a >= 0 ? a : ''; } }">
            @csrf
            <x-ui.card title="Beneficiary Information" class="pb-[12px]">
                <div class="divider mt-[7px]"></div>

                <div class="mt-[9.5px] grid grid-cols-2 gap-x-[29px]">
                    <x-beneficiary.form-field label="First Name" name="first_name" :value="old('first_name')" placeholder="Juan" :error="$errors->first('first_name')" required />
                    <x-beneficiary.form-field label="Address" name="address" :value="old('address')" placeholder="House No / Purok / Street" :error="$errors->first('address')" required />
                    <x-beneficiary.form-field label="Middle Name" name="middle_name" :value="old('middle_name')" placeholder="Abenoja" :error="$errors->first('middle_name')" />
                    <x-beneficiary.form-field label="Barangay" name="barangay_id" :error="$errors->first('barangay_id')">
                        <select id="field-barangay_id" name="barangay_id" required
                                class="h-[39px] w-full cursor-pointer appearance-none rounded-[15px] bg-brand-soft/10 px-[16px] text-[14px] font-medium text-ink outline-none invalid:text-muted">
                            <option value="" disabled @selected(! old('barangay_id'))>Poblacion</option>
                            @foreach ($barangays as $barangay)
                                <option value="{{ $barangay->id }}" @selected((string) old('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>
                            @endforeach
                        </select>
                        <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="pointer-events-none absolute top-[10.5px] right-[14px] size-[18px]">
                    </x-beneficiary.form-field>
                    <x-beneficiary.form-field label="Last Name" name="last_name" :value="old('last_name')" placeholder="Dela Cruz" :error="$errors->first('last_name')" required />
                    <x-beneficiary.form-field label="Contact Number" name="contact_number" :value="old('contact_number')" placeholder="09XX-XXX-XXXX" :error="$errors->first('contact_number')" />
                    <x-beneficiary.form-field label="Birthdate" name="birthdate" type="date" :value="old('birthdate')" :error="$errors->first('birthdate')" x-model="birthdate" required />
                    <x-beneficiary.form-field label="Farm Location" name="farm_location" :value="old('farm_location')" placeholder="Poblacion (1.5 hectares)" :error="$errors->first('farm_location')" />
                    <x-beneficiary.form-field label="Age" name="age_display">
                        <input id="field-age_display" type="text" readonly tabindex="-1" :value="age" placeholder="Must be 18 years old or above"
                               class="h-[39px] w-full rounded-[15px] bg-brand-soft/10 px-[16px] text-[14px] font-medium text-ink outline-none placeholder:text-muted">
                    </x-beneficiary.form-field>
                    <x-beneficiary.form-field label="Crop Type" name="crop_type" :value="old('crop_type')" placeholder="Cabbage" :error="$errors->first('crop_type')" />
                    <x-beneficiary.form-field label="RSBSA No." name="rsbsa_number" :value="old('rsbsa_number')" placeholder="Leave blank if none (shows N/A)" :error="$errors->first('rsbsa_number')" />
                </div>
            </x-ui.card>

            <button type="submit" :disabled="busy" class="bg-brand-bar gradient-button disabled:cursor-wait disabled:opacity-70 flex h-[59px] w-full items-center justify-center gap-[6px] rounded-[50px] text-[20px] font-bold leading-[24px] text-white">
                <span class="text-[24px] leading-none">+</span> Add Beneficiary
            </button>
        </form>
</x-layouts.app>
