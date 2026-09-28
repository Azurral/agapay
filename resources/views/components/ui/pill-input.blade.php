@props(['name', 'placeholder' => '', 'value' => null, 'width' => 272])
<label class="relative block h-[39px] shrink-0" style="width: {{ $width }}px">
    <span class="sr-only">{{ $placeholder }}</span>
    <input type="search" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}"
           class="h-[39px] w-full rounded-[50px] border-[1.5px] border-black/15 bg-white pr-[36px] pl-[13.5px] text-[14px] font-medium text-ink outline-none placeholder:text-muted focus:border-brand-soft">
    <img src="{{ asset('images/figma/icons/search.svg') }}" alt="" class="pointer-events-none absolute top-[11px] right-[14px] size-[16px]">
</label>
