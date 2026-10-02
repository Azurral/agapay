@props(['name', 'label', 'options' => [], 'selected' => null, 'width' => 280])
<label class="relative block h-[39px] shrink-0" style="width: {{ $width }}px">
    <span class="sr-only">{{ $label }}</span>
    <select name="{{ $name }}" onchange="this.form.requestSubmit()"
            class="h-[39px] w-full cursor-pointer appearance-none rounded-[50px] border-[1.5px] border-black/15 bg-white pr-[36px] pl-[16px] text-[14px] font-medium text-muted outline-none focus:border-brand-soft">
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $label }}: {{ $text }}</option>
        @endforeach
    </select>
    <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="pointer-events-none absolute top-[10.5px] right-[14.5px] size-[18px]">
</label>
