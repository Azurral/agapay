@php
    $editing = $report !== null;
    $box = 'flex h-[44px] items-center rounded-[10px] border bg-white px-[16px] text-[14px] font-bold leading-[20px]';
    $control = 'ml-[6px] h-full min-w-0 flex-1 bg-transparent font-bold outline-none placeholder:text-muted';
    $border = fn (string ...$fields) => $errors->hasAny($fields) ? 'border-bad' : 'border-field';
    $fieldError = fn (string $field) => $errors->first($field);
    // Old input is used only when it is a plain value: a forged array falls back to the saved or default value.
    $old = fn (string $field, $default = '') => is_scalar($input = old($field)) ? $input : $default;
    $value = fn (string $field, $default = '') => $old($field, $report?->{$field} ?? $default);
    $area = fn (string $field) => $old($field, $report ? rtrim(rtrim((string) $report->{$field}, '0'), '.') : '');
    $pickedId = $old('beneficiary_id', $report?->beneficiary_id);
    $picked = $pickedId && ctype_digit((string) $pickedId) ? \App\Models\Beneficiary::find((int) $pickedId) : null;
    $cropValues = $crops->mapWithKeys(fn ($c) => [$c->id => [
        'name' => $c->name, 'yield' => (float) $c->yield_mt_per_ha, 'price' => (float) $c->price_per_mt, 'factor' => (float) $c->partial_loss_factor,
    ]]);
    $photoErrors = collect($errors->getMessages())->filter(fn ($m, $key) => str_starts_with($key, 'photos'))->flatten()->unique();
@endphp
<x-layouts.app :title="$editing ? 'EDIT DAMAGE REPORT' : 'NEW DAMAGE REPORT'">
    @if ($tooLarge)
        <p class="flash rounded-[10px] border-[1.5px] border-bad bg-white px-[16px] py-[10px] text-[14px] font-bold text-danger" role="alert">The photos are too large to upload at once — up to 10 photos of 5 MB each.</p>
    @endif
    @error('report')
        <p class="flash rounded-[10px] border-[1.5px] border-bad bg-white px-[16px] py-[10px] text-[14px] font-bold text-danger" role="alert">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ $editing ? route('damage.update', $report) : route('damage.store') }}" enctype="multipart/form-data"
          x-data="{
              crops: @js($cropValues), crop: @js((string) $value('crop_id')), total: @js((string) $area('total_area_ha')), partial: @js((string) $area('partial_area_ha')),
              barangay: @js((string) $value('barangay_id')), farm: @js((string) $value('farm_location')),
              lat: @js((string) $old('latitude', $report?->latitude ? (float) $report->latitude : '')), lng: @js((string) $old('longitude', $report?->longitude ? (float) $report->longitude : '')),
              locating: false, locateError: '', files: [], over: false, busy: false,
              get estimate() {
                  const c = this.crops[this.crop]; const t = parseFloat(this.total) || 0; const p = parseFloat(this.partial) || 0;
                  if (!c || t + p <= 0) return null;
                  const loss = Math.round((t + c.factor * p) * c.yield * 100) / 100;
                  return { loss, cost: Math.round(loss * c.price * 100) / 100, crop: c };
              },
              locate() {
                  if (!navigator.geolocation) { this.locateError = 'This browser cannot share its location.'; return; }
                  this.locating = true; this.locateError = '';
                  navigator.geolocation.getCurrentPosition(
                      (p) => { this.lat = p.coords.latitude.toFixed(6); this.lng = p.coords.longitude.toFixed(6); this.locating = false; },
                      () => { this.locateError = 'Location not available. Type the coordinates instead.'; this.locating = false; },
                      { enableHighAccuracy: true, timeout: 10000 },
                  );
              },
              photoNote: '',
              // Checked here because PHP drops an over-limit request before Laravel sees it (and with it every typed field).
              add(list) {
                  const maxFile = Number(this.$root.dataset.maxPhotoBytes), maxPost = Number(this.$root.dataset.maxPostBytes) - 262144;
                  const skipped = [];
                  for (const file of list) {
                      const total = this.files.reduce((sum, f) => sum + f.file.size, 0) + file.size;
                      if (this.files.length >= 10) { skipped.push(file.name + ' (10 photos at most)'); continue; }
                      if (!/\.(jpe?g|png)$/i.test(file.name)) { skipped.push(file.name + ' (not JPG/PNG)'); continue; }
                      if (file.size > maxFile) { skipped.push(file.name + ' (over ' + this.$root.dataset.maxPhotoLabel + ')'); continue; }
                      if (maxPost > 0 && total > maxPost) { skipped.push(file.name + ' (the photos together are too large to send)'); continue; }
                      this.files.push({ file, url: URL.createObjectURL(file) });
                  }
                  this.photoNote = skipped.length ? 'Not added: ' + skipped.join(', ') + '.' : '';
                  this.sync();
              },
              remove(index) { URL.revokeObjectURL(this.files[index].url); this.files.splice(index, 1); this.sync(); },
              sync() { const dt = new DataTransfer(); this.files.forEach((f) => dt.items.add(f.file)); this.$refs.photos.files = dt.files; },
          }" @submit="busy = true" class="flex items-start gap-[21px]"
          data-max-photo-bytes="{{ \App\Http\Requests\DamageReportRequest::photoLimitBytes() }}"
          data-max-photo-label="{{ \App\Http\Requests\DamageReportRequest::photoLimitLabel() }}"
          data-max-post-bytes="{{ \App\Http\Requests\DamageReportRequest::bytes((string) ini_get('post_max_size')) }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- Figma 423:1197 --}}
        <section class="w-[899px] shrink-0 rounded-[20px] border-[1.5px] border-black/10 bg-white pt-[19.5px] pr-[24.5px] pb-[31.5px] pl-[22.5px]">
            <header class="flex items-center gap-[5px]">
                <img src="{{ asset('images/figma/icons/task.svg') }}" alt="" class="size-[18px]">
                <h2 class="text-[16px] font-bold leading-[20px]">{{ $editing ? 'Edit Damage Report' : 'New Damage Report' }}</h2>
            </header>
            <div class="divider mt-[15px] ml-[2px]"></div>

            <div class="mt-[33px] grid grid-cols-2 gap-x-[12px] gap-y-[12px]">
                <div>
                    <label @class([$box, $border('disaster_id')])>
                        <span class="shrink-0">Crisis:</span>
                        <select name="disaster_id" required class="{{ $control }} cursor-pointer">
                            @foreach ($disasters as $disaster)
                                <option value="{{ $disaster->id }}" @selected((string) $value('disaster_id', $disasters->first()?->id) === (string) $disaster->id)>{{ $disaster->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($fieldError('disaster_id'))<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $fieldError('disaster_id') }}</p>@endif
                </div>
                <div>
                    <label @class([$box, $border('barangay_id')])>
                        <span class="shrink-0">Barangay:</span>
                        <select name="barangay_id" x-model="barangay" required class="{{ $control }} cursor-pointer" :class="barangay === '' && 'text-muted'">
                            <option value="" disabled>Select</option>
                            @foreach ($barangays as $barangay)
                                <option value="{{ $barangay->id }}">{{ $barangay->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($fieldError('barangay_id'))<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $fieldError('barangay_id') }}</p>@endif
                </div>

                {{-- Farmer: existing profiles only; picking one prefills barangay, farm and crop. --}}
                <div class="relative" x-data="{
                        q: @js($picked?->fullName() ?? ''), id: @js((string) ($pickedId ?? '')), results: [], open: false,
                        async search() {
                            this.id = '';
                            if (this.q.trim().length < 2) { this.results = []; this.open = false; return; }
                            const res = await fetch(@js(route('beneficiaries.lookup')) + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
                            this.results = res.ok ? await res.json() : []; this.open = true;
                        },
                        pick(r) {
                            this.id = String(r.id); this.q = r.name; this.open = false;
                            if (r.barangay_id) barangay = String(r.barangay_id);
                            if (r.address && farm === '') farm = r.address;
                            const match = Object.entries(crops).find(([, c]) => c.name.toLowerCase() === String(r.crop_type ?? '').toLowerCase());
                            if (match && crop === '') crop = match[0];
                        },
                    }" @click.outside="open = false">
                    <label @class([$box, $border('beneficiary_id')])>
                        <span class="shrink-0">Farmer / Beneficiary:</span>
                        <input type="text" x-model="q" @input.debounce.250ms="search()" autocomplete="off" placeholder="Name" class="{{ $control }}">
                    </label>
                    <input type="hidden" name="beneficiary_id" :value="id">
                    <ul x-cloak x-show="open" class="absolute top-[48px] right-0 left-0 z-30 max-h-[260px] overflow-auto rounded-[10px] border border-field bg-white py-[4px] text-[14px] shadow-[0_8px_24px_rgba(90,93,227,0.18)]">
                        <template x-for="r in results" :key="r.id">
                            <li><button type="button" @click="pick(r)" class="hover-tint flex w-full gap-[10px] px-[16px] py-[8px] text-left">
                                <span class="font-bold" x-text="r.name"></span><span class="text-muted" x-text="r.rsbsa + ' · ' + (r.barangay ?? '')"></span>
                            </button></li>
                        </template>
                        <li x-show="results.length === 0" class="px-[16px] py-[8px] text-muted">No farmer found. Register them first under Add Beneficiary.</li>
                    </ul>
                    @if ($fieldError('beneficiary_id'))
                        <p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">
                            {{ $fieldError('beneficiary_id') }}
                            @if ($errors->has('existing_report'))<a href="{{ $errors->first('existing_report') }}" class="text-brand underline">Open the existing report</a>@endif
                        </p>
                    @endif
                </div>
                <div>
                    <label @class([$box, $border('crop_id', 'farm_location')])>
                        <span class="shrink-0">Crop / Farm Location:</span>
                        <select name="crop_id" x-model="crop" required class="{{ $control }} max-w-[120px] flex-none cursor-pointer" :class="crop === '' && 'text-muted'">
                            <option value="" disabled>Select</option>
                            @foreach ($crops as $crop)
                                <option value="{{ $crop->id }}">{{ $crop->name }}</option>
                            @endforeach
                        </select>
                        <span class="mx-[6px] text-muted">·</span>
                        <input type="text" name="farm_location" x-model="farm" maxlength="255" placeholder="Farm location" class="h-full min-w-0 flex-1 bg-transparent font-bold outline-none placeholder:text-muted">
                    </label>
                    @foreach (['crop_id', 'farm_location'] as $field)
                        @if ($fieldError($field))<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $fieldError($field) }}</p>@endif
                    @endforeach
                </div>
            </div>

            <div class="mt-[12px] grid grid-cols-3 gap-x-[12px]">
                <div>
                    <label @class([$box, $border('crop_stage')])>
                        <span class="shrink-0">Crop Stage:</span>
                        <select name="crop_stage" required class="{{ $control }} cursor-pointer">
                            <option value="" disabled @selected(! $value('crop_stage'))>Select</option>
                            @foreach (\App\Models\DamageReport::STAGES as $stage => $label)
                                <option value="{{ $stage }}" @selected($value('crop_stage') === $stage)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($fieldError('crop_stage'))<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $fieldError('crop_stage') }}</p>@endif
                </div>
                <x-ui.inline-field label="Total Area" name="total_area_ha" type="text" inputmode="decimal" placeholder="(ha)" x-model="total" :error="$fieldError('total_area_ha')" />
                <x-ui.inline-field label="Partial Area" name="partial_area_ha" type="text" inputmode="decimal" placeholder="(ha)" x-model="partial" :error="$fieldError('partial_area_ha')" />
            </div>

            <div class="mt-[22px] flex items-baseline justify-between">
                <h3 class="text-[16px] font-bold leading-[20px]">GPS Coordinates</h3>
                <button type="button" @click="locate()" class="text-[13px] font-bold text-brand hover:underline" x-text="locating ? 'Finding your location…' : 'Use my location'"></button>
            </div>
            <p x-cloak x-show="locateError" x-text="locateError" class="mt-[4px] text-[12px] font-semibold text-danger"></p>
            <div class="mt-[8px] grid grid-cols-2 gap-x-[12px]">
                <x-ui.inline-field label="Latitude" name="latitude" type="text" inputmode="decimal" placeholder="(e.g. 17.0894)" x-model="lat" :error="$fieldError('latitude')" />
                <x-ui.inline-field label="Longitude" name="longitude" type="text" inputmode="decimal" placeholder="(e.g. 120.9750)" x-model="lng" :error="$fieldError('longitude')" />
            </div>

            <h3 class="mt-[18px] text-[16px] font-bold leading-[20px]">Photo / Attachment</h3>
            @if ($editing && $report->photos->isNotEmpty())
                <div class="mt-[8px] flex flex-wrap gap-[10px]">
                    @foreach ($report->photos as $photo)
                        <label class="relative block size-[84px] cursor-pointer overflow-hidden rounded-[10px] border border-field" x-data="{ removed: false }">
                            <img src="{{ route('damage.photos.show', $photo) }}" alt="{{ $photo->original_name }}" class="size-full object-cover" :class="removed && 'opacity-30'">
                            <input type="checkbox" name="remove_photos[]" value="{{ $photo->id }}" x-model="removed" class="sr-only">
                            <span class="absolute right-[4px] bottom-[4px] rounded-[6px] bg-white/90 px-[6px] text-[11px] font-bold" x-text="removed ? 'Keep' : 'Remove'"></span>
                        </label>
                    @endforeach
                </div>
            @endif
            @php($staged = $errors->any() ? \App\Support\StagedPhotos::all() : [])
            @if ($staged)
                <div class="mt-[8px] flex flex-wrap gap-[10px]">
                    @foreach ($staged as $token => $kept)
                        <span x-data="{ kept: true }" x-show="kept" class="relative block size-[84px] overflow-hidden rounded-[10px] border-2 border-ok bg-white">
                            <img src="{{ route('damage.staged-photos.show', $token) }}" alt="{{ $kept['name'] }}" class="size-full object-cover">
                            <input type="hidden" name="kept_photos[]" value="{{ $token }}" :disabled="! kept">
                            <button type="button" @click="kept = false" class="absolute top-[4px] right-[4px] rounded-full bg-white/90 px-[6px] text-[12px] font-bold" aria-label="Remove {{ $kept['name'] }}">✕</button>
                        </span>
                    @endforeach
                </div>
            @endif
            <label @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="over = false; add([...$event.dataTransfer.files])"
                   :class="over ? 'border-brand bg-brand-soft/20' : 'border-black/15 bg-brand-soft/10'"
                   class="mt-[8px] flex min-h-[130px] cursor-pointer flex-col items-center justify-center rounded-[15px] border-2 border-dashed px-[16px] py-[16px] text-center transition-colors">
                <input x-ref="photos" type="file" name="photos[]" accept=".jpg,.jpeg,.png,image/jpeg,image/png" multiple class="sr-only"
                       @change="add([...$event.target.files])">
                <span x-show="files.length === 0" class="text-[16px] font-bold leading-[20px]">Drag &amp; drop photos here (JPG/PNG)</span>
                <span x-show="files.length === 0" class="mt-[2px] text-[13px] leading-[17px] text-black">or click to browse</span>
                <span x-cloak x-show="files.length > 0" class="flex flex-wrap justify-center gap-[10px]">
                    <template x-for="(f, i) in files" :key="f.url">
                        <span class="relative block size-[84px] overflow-hidden rounded-[10px] border border-field bg-white">
                            <img :src="f.url" :alt="f.file.name" class="size-full object-cover">
                            <button type="button" @click.prevent="remove(i)" class="absolute top-[4px] right-[4px] rounded-full bg-white/90 px-[6px] text-[12px] font-bold" aria-label="Remove photo">✕</button>
                        </span>
                    </template>
                </span>
            </label>
            @foreach ($photoErrors as $message)
                <p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>
            @endforeach
            <p x-cloak x-show="photoNote" x-text="photoNote" class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger"></p>
            @if ($staged)
                <p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-[#1f7a35]">Your photos are kept (green border) — remove any you do not want, or add more.</p>
            @endif
            <p class="mt-[4px] pl-[4px] text-[12px] font-medium text-muted">At least 1 and up to 10 JPG or PNG photos, each at most {{ \App\Http\Requests\DamageReportRequest::photoLimitLabel() }}.</p>
            <div class="mt-[16px] flex justify-center">
                <button type="button" @click="$refs.photos.click()"
                        class="bg-brand-bar gradient-button flex h-[39px] w-[220px] items-center justify-center gap-[2px] rounded-[50px] text-[20px] font-bold leading-[24px] text-white">
                    <img src="{{ asset('images/figma/icons/plus.svg') }}" alt="" class="size-[23px] brightness-0 invert"> Choose Photo
                </button>
            </div>

            <button type="submit" :disabled="busy"
                    class="bg-brand-bar gradient-button mt-[37px] h-[48px] w-full rounded-[50px] text-[20px] font-bold leading-[24px] text-white">{{ $editing ? 'Save Changes' : 'Submit Damage Report' }}</button>
        </section>

        {{-- Not in Figma: the computed figures the report will carry (spec rule 11). --}}
        <aside class="w-[320px] shrink-0 rounded-[20px] border-[1.5px] border-black/10 bg-white p-[20px]">
            <h2 class="text-[16px] font-bold leading-[20px]">Estimated Loss</h2>
            <template x-if="estimate">
                <div class="mt-[14px]">
                    <p class="text-[30px] leading-[38px] font-bold text-stat-4"><span x-text="estimate.loss.toLocaleString('en-PH', { maximumFractionDigits: 2 })"></span> MT</p>
                    <p class="text-[30px] leading-[38px] font-bold text-stat-3">₱<span x-text="estimate.cost.toLocaleString('en-PH', { maximumFractionDigits: 2 })"></span></p>
                    <p class="mt-[10px] text-[12px] leading-[17px] font-medium text-muted">
                        (Total + <span x-text="estimate.crop.factor"></span> × Partial) × <span x-text="estimate.crop.yield"></span> MT/ha,
                        × ₱<span x-text="estimate.crop.price.toLocaleString('en-PH')"></span>/MT for <span x-text="estimate.crop.name"></span>.
                        Validation may adjust these figures.
                    </p>
                </div>
            </template>
            <p x-show="! estimate" class="mt-[14px] text-[13px] leading-[18px] font-medium text-muted">Choose a crop and enter the damaged area to see the production loss and cost.</p>
        </aside>
    </form>
</x-layouts.app>
