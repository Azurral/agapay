@props(['label', 'name', 'value' => null, 'type' => 'text', 'placeholder' => '', 'error' => null])
{{-- Figma 329:3298 "Fill up" field: bold 16px label, 39px lavender field, radius 15. --}}
<div class="flex flex-col">
    <label for="field-{{ $name }}" class="h-[20px] text-[16px] font-bold leading-[20px]">{{ $label }}</label>
    <div class="relative mt-[7px]">
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            <input id="field-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" placeholder="{{ $placeholder }}"
                   {{ $attributes->class([
                       'h-[39px] w-full rounded-[15px] bg-brand-soft/10 px-[16px] text-[14px] font-medium text-ink outline-none placeholder:text-muted focus:ring-2 focus:ring-brand-soft/40',
                       'ring-2 ring-bad' => $error,
                   ]) }}>
        @endif
    </div>
    <p class="mt-[2px] min-h-[7px] text-[12px] font-semibold leading-[16px] text-danger">{{ $error }}</p>
</div>
