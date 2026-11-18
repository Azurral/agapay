@php
    use App\Models\Beneficiary;

    $locked = 'h-[39px] w-full cursor-not-allowed rounded-[15px] bg-field/60 px-[16px] text-[14px] font-medium text-ink outline-none';
@endphp
<x-layouts.app title="ADD BENEFICIARY">
    @if (session('status'))
        <p class="flash rounded-[10px] border-[1.5px] border-ok bg-white px-[16px] py-[10px] text-[14px] font-bold" role="status" data-autohide>{{ session('status') }}</p>
    @endif

    {{-- Figma 329:3290 form card + 329:3330 submit bar (20px apart); the address follows the RSBSA form's parts. --}}
    <form method="POST" action="{{ route('beneficiaries.store') }}" enctype="multipart/form-data" class="flex flex-col gap-[20px]"
          x-on:submit="busy = true"
          x-data="{ busy: false, birthdate: @js(old('birthdate', '')), get age() { if (! this.birthdate) return ''; const b = new Date(this.birthdate), n = new Date(); let a = n.getFullYear() - b.getFullYear(); if (n.getMonth() < b.getMonth() || (n.getMonth() === b.getMonth() && n.getDate() < b.getDate())) a--; return a >= 0 ? a : ''; } }">
        @csrf
        <x-ui.card title="Beneficiary Information" class="pb-[12px]">
            <div class="divider mt-[7px]"></div>

            <div class="mt-[9.5px] grid grid-cols-2 gap-x-[29px]">
                <div class="flex flex-col">
                    <x-beneficiary.form-field label="First Name" name="first_name" :value="old('first_name')" placeholder="Juan" :error="$errors->first('first_name')" required />
                    <x-beneficiary.form-field label="Middle Name" name="middle_name" :value="old('middle_name')" placeholder="Abenoja" :error="$errors->first('middle_name')" />
                    <x-beneficiary.form-field label="Last Name" name="last_name" :value="old('last_name')" placeholder="Dela Cruz" :error="$errors->first('last_name')" required />
                    <x-beneficiary.form-field label="Birthdate" name="birthdate" type="date" :value="old('birthdate')" :error="$errors->first('birthdate')" x-model="birthdate" required />
                    <x-beneficiary.form-field label="Age" name="age_display">
                        <input id="field-age_display" type="text" readonly tabindex="-1" :value="age" placeholder="Must be 18 years old or above"
                               class="h-[39px] w-full rounded-[15px] bg-brand-soft/10 px-[16px] text-[14px] font-medium text-ink outline-none placeholder:text-muted">
                    </x-beneficiary.form-field>
                    <x-beneficiary.form-field label="Contact Number" name="contact_number" :value="old('contact_number')" placeholder="09XX-XXX-XXXX" :error="$errors->first('contact_number')" />
                    <x-beneficiary.form-field label="RSBSA No." name="rsbsa_number" :value="old('rsbsa_number')" placeholder="Leave blank if none (shows N/A)" :error="$errors->first('rsbsa_number')" />
                </div>

                <div class="flex flex-col">
                    <x-beneficiary.form-field label="House/Lot No." name="house_no" :value="old('house_no')" placeholder="12" :error="$errors->first('house_no')" />
                    <x-beneficiary.form-field label="Street" name="street" :value="old('street')" placeholder="Rizal St." :error="$errors->first('street')" />
                    <x-beneficiary.form-field label="Sitio/Purok" name="sitio" :value="old('sitio')" placeholder="Purok 3" :error="$errors->first('sitio')" required />
                    <x-beneficiary.form-field label="Barangay" name="barangay_id" :error="$errors->first('barangay_id')">
                        <select id="field-barangay_id" name="barangay_id" required
                                class="h-[39px] w-full cursor-pointer appearance-none rounded-[15px] bg-brand-soft/10 px-[16px] text-[14px] font-medium text-ink outline-none invalid:text-muted">
                            <option value="" disabled @selected(! old('barangay_id'))>Select barangay</option>
                            @foreach ($barangays as $barangay)
                                <option value="{{ $barangay->id }}" @selected((string) old('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>
                            @endforeach
                        </select>
                        <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="pointer-events-none absolute top-[10.5px] right-[14px] size-[18px]">
                    </x-beneficiary.form-field>
                    @foreach (['municipality' => ['Municipality', Beneficiary::MUNICIPALITY], 'province' => ['Province', Beneficiary::PROVINCE], 'region' => ['Region', Beneficiary::REGION]] as $key => [$label, $value])
                        <x-beneficiary.form-field :label="$label" :name="$key">
                            <input id="field-{{ $key }}" type="text" value="{{ $value }}" readonly tabindex="-1" title="AGAPAY covers the barangays of Bontoc only." class="{{ $locked }}">
                        </x-beneficiary.form-field>
                    @endforeach
                </div>

                <x-beneficiary.form-field label="Farm Area (ha)" name="farm_area_ha" type="number" step="0.01" min="0" :value="old('farm_area_ha')" placeholder="1.5" :error="$errors->first('farm_area_ha')" />
                <x-beneficiary.form-field label="Crop Type" name="crop_type" :value="old('crop_type')" placeholder="Cabbage" :error="$errors->first('crop_type')" />
            </div>

            {{-- Geolocation: every farm has its GPS position; crisis damage photos are checked against it. --}}
            <div class="mt-[6px] grid grid-cols-[1fr_1fr_auto] items-end gap-x-[29px]"
                 x-data="{ lat: @js((string) old('farm_latitude', '')), lng: @js((string) old('farm_longitude', '')), locating: false, problem: '',
                           locate() { if (! navigator.geolocation) { this.problem = 'This browser cannot read the location; type the coordinates.'; return; }
                                      this.locating = true; this.problem = '';
                                      navigator.geolocation.getCurrentPosition(
                                          (p) => { this.lat = p.coords.latitude.toFixed(6); this.lng = p.coords.longitude.toFixed(6); this.locating = false; },
                                          () => { this.locating = false; this.problem = 'The location could not be read; type the coordinates (e.g. from Google Maps).'; },
                                          { enableHighAccuracy: true, timeout: 15000 }); } }">
                <x-beneficiary.form-field label="Farm Latitude" name="farm_latitude" placeholder="17.0894" :error="$errors->first('farm_latitude')" x-model="lat" required />
                <x-beneficiary.form-field label="Farm Longitude" name="farm_longitude" placeholder="120.9770" :error="$errors->first('farm_longitude')" x-model="lng" required />
                <div class="mb-[12px] flex flex-col gap-[4px]">
                    <button type="button" @click="locate()" :disabled="locating"
                            class="border-gradient pill-button h-[39px] rounded-[50px] px-[16px] text-[13px] font-bold disabled:opacity-60">
                        <span x-show="! locating">Use my location</span><span x-cloak x-show="locating">Locating&hellip;</span>
                    </button>
                    <a x-show="lat && lng" x-cloak :href="`https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=16/${lat}/${lng}`" target="_blank" rel="noopener"
                       class="text-center text-[12px] font-bold text-brand hover:underline">Check on the map</a>
                </div>
                <p x-show="problem" x-cloak x-text="problem" class="col-span-3 text-[12px] font-bold text-danger"></p>
                <p class="col-span-3 -mt-[6px] text-[12px] text-arrow">Stand at the farm and press Use my location, or type the coordinates from a map. Crisis damage photos must be taken within {{ rtrim(rtrim(number_format(config('agapay.geo.max_km'), 1), '0'), '.') }} km of this point.</p>
            </div>
        </x-ui.card>

        {{-- No ghost farmers: a walk-in shows a valid ID; the Administrator checks it before any program is given. --}}
        <x-ui.card title="Valid ID" class="pb-[12px]">
            <div class="divider mt-[7px]"></div>
            <div class="mt-[9.5px] grid grid-cols-3 gap-x-[29px]">
                <x-beneficiary.form-field label="ID Type" name="valid_id_type" :error="$errors->first('valid_id_type')">
                    <select id="field-valid_id_type" name="valid_id_type" required
                            class="h-[39px] w-full cursor-pointer appearance-none rounded-[15px] bg-brand-soft/10 px-[16px] text-[14px] font-medium text-ink outline-none invalid:text-muted">
                        <option value="" disabled @selected(! old('valid_id_type'))>Select the ID shown</option>
                        @foreach (Beneficiary::VALID_IDS as $value => $label)
                            <option value="{{ $value }}" @selected(old('valid_id_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="pointer-events-none absolute top-[10.5px] right-[14px] size-[18px]">
                </x-beneficiary.form-field>
                <x-beneficiary.form-field label="ID Number" name="valid_id_number" :value="old('valid_id_number')" placeholder="As printed on the ID" :error="$errors->first('valid_id_number')" required />
                <x-beneficiary.form-field label="ID Photo" name="valid_id_photo" :error="$errors->first('valid_id_photo')">
                    <input id="field-valid_id_photo" name="valid_id_photo" type="file" accept="image/jpeg,image/png" required
                           class="h-[39px] w-full cursor-pointer rounded-[15px] bg-brand-soft/10 px-[10px] py-[7px] text-[13px] font-medium text-ink outline-none file:mr-[10px] file:rounded-full file:border-0 file:bg-white file:px-[12px] file:text-[12px] file:font-bold">
                </x-beneficiary.form-field>
            </div>
            @cannot('beneficiaries.approve')
                <p class="mt-[6px] text-[12px] text-arrow">This walk-in registration will wait for the Administrator's approval before any program can be given.</p>
            @endcannot
        </x-ui.card>

        <x-beneficiary.consent-check :text="config('agapay.consent_text')" :error="$errors->first('consent')" />

        <button type="submit" :disabled="busy" class="bg-brand-bar gradient-button disabled:cursor-wait disabled:opacity-70 flex h-[59px] w-full items-center justify-center gap-[6px] rounded-[50px] text-[20px] font-bold leading-[24px] text-white">
            <span class="text-[24px] leading-none">+</span> Add Beneficiary
        </button>
    </form>
</x-layouts.app>
