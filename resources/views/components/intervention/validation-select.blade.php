@props(['record'])
@php
    use App\Models\InterventionRecord;

    $tone = InterventionRecord::validationTone($record->validation_status);
    $direct = auth()->user()->can('beneficiaries.approve');   // the Administrator's change applies at once
@endphp
{{-- Proof of eligibility: picking another status opens a small panel for the proof file (a signed certification or
     validation sheet); an Agricultural Technologist's change then waits for the Administrator. --}}
<form method="POST" action="{{ route('intervention-records.validate', $record) }}" enctype="multipart/form-data" class="relative w-[155px]"
      x-data="{ current: @js($record->validation_status), picked: @js($record->validation_status) }" @keydown.escape="picked = current">
    @csrf
    <label class="relative block">
        <span class="sr-only">Validation status</span>
        <select name="validation_status" x-model="picked"
                @class([
                    'h-[39px] w-full cursor-pointer appearance-none rounded-[10px] border-4 bg-white pr-[30px] pl-[14px] text-center text-[14px] font-bold leading-[24px] outline-none [text-align-last:center] focus-visible:ring-2 focus-visible:ring-brand-soft',
                    'border-ok' => $tone === 'ok',
                    'border-bad' => $tone === 'bad',
                ])>
            @foreach (InterventionRecord::VALIDATIONS as $value => $label)
                <option value="{{ $value }}" @selected($record->validation_status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="pointer-events-none absolute top-[10.5px] right-[12px] size-[18px]">
    </label>
    <div x-show="picked !== current" x-cloak x-transition.opacity
         class="absolute top-[44px] right-0 z-30 w-[300px] rounded-[12px] border border-black/10 bg-white p-[12px] text-left text-[12px] font-medium shadow-lg">
        <p class="font-bold">Proof for <span x-text="@js(InterventionRecord::VALIDATIONS)[picked]"></span></p>
        <p class="mt-[2px] text-muted">A signed barangay certification or validation sheet, as a photo or PDF.</p>
        <input type="file" name="proof" accept="image/jpeg,image/png,application/pdf" :required="picked !== current"
               class="mt-[8px] w-full text-[12px] file:mr-[8px] file:rounded-full file:border-0 file:bg-tint file:px-[10px] file:py-[4px] file:font-bold">
        <div class="mt-[10px] flex gap-[8px]">
            <button type="submit" class="bg-brand-button gradient-button h-[32px] flex-1 rounded-[50px] text-[12px] font-bold text-white">{{ $direct ? 'Save' : 'Send for approval' }}</button>
            <button type="button" @click="picked = current" class="h-[32px] rounded-[50px] border border-black/15 px-[12px] text-[12px] font-bold">Cancel</button>
        </div>
    </div>
</form>
