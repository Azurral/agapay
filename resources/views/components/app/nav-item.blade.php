@props(['item', 'index' => 0])
{{-- nav-enter: slides in after the one above it each time the panel opens; the icon nudges right on hover. --}}
<a href="{{ $item['url'] }}" @click="nav = false" @if ($item['active']) aria-current="page" @endif style="--i: {{ $index }}"
   @class([
       'nav-enter relative block h-[42px] w-[235px] shrink-0 rounded-[13px]',
       'hover-tint' => ! $item['active'],
       'bg-[#efeaff] text-brand before:absolute before:top-[9px] before:left-0 before:h-[24px] before:w-[4px] before:rounded-full before:bg-brand' => $item['active'],
   ])>
    <span class="nav-icon absolute top-[9px] left-[14px] flex size-[24px] items-center justify-center">
        <img src="{{ asset('images/figma/icons/'.$item['icon'].'.svg') }}" alt="">
    </span>
    <span class="absolute top-[11px] left-[47px] text-[16px] font-bold leading-[20px]">{{ $item['label'] }}</span>
</a>
