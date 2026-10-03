@props(['name', 'options', 'selected', 'tone' => 'ok', 'action' => null, 'actions' => []])
{{-- Figma 407:323 dropdown chip: the 155x39 status chip as a select that submits on change.
     `actions` maps an option value to its own URL (claim / unclaim); otherwise the form posts to `action`. --}}
<form method="POST" action="{{ $action ?? reset($actions) }}" class="w-[155px]"
      x-data="{ urls: @js($actions) }">
    @csrf
    <label class="relative block">
        <span class="sr-only">{{ \Illuminate\Support\Str::headline($name) }}</span>
        <select name="{{ $name }}"
                @change="if (urls[$event.target.value]) { $el.form.action = urls[$event.target.value] } $el.form.requestSubmit()"
                @class([
                    'h-[39px] w-full cursor-pointer appearance-none rounded-[10px] border-4 bg-white pr-[30px] pl-[14px] text-center text-[14px] font-bold leading-[24px] outline-none [text-align-last:center] focus-visible:ring-2 focus-visible:ring-brand-soft',
                    'border-ok' => $tone === 'ok',
                    'border-bad' => $tone === 'bad',
                ])>
            @foreach ($options as $value => $label)
                <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="pointer-events-none absolute top-[10.5px] right-[12px] size-[18px]">
    </label>
</form>
