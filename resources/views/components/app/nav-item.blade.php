@props(['item'])
<a href="{{ $item['url'] }}" @click="nav = false" @if ($item['active']) aria-current="page" @endif
   @class([
       'relative block h-[51px] w-[235px] shrink-0 rounded-[15px]',
       'hover-tint' => ! $item['active'],
       'bg-[#efeaff] text-brand before:absolute before:top-[12px] before:left-0 before:h-[27px] before:w-[4px] before:rounded-full before:bg-brand' => $item['active'],
   ])>
    <span class="absolute top-[14px] left-[14px] flex size-[24px] items-center justify-center">
        <img src="{{ asset('images/figma/icons/'.$item['icon'].'.svg') }}" alt="">
    </span>
    <span class="absolute top-[16px] left-[47px] text-[16px] font-bold leading-[20px]">{{ $item['label'] }}</span>
</a>
