@props(['item'])
<a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
   class="relative block h-[51px] w-[235px] shrink-0 hover-tint rounded-[15px]">
    <span class="absolute top-[14px] left-[14px] flex size-[24px] items-center justify-center">
        <img src="{{ asset('images/figma/icons/'.$item['icon'].'.svg') }}" alt="">
    </span>
    <span class="absolute top-[16px] left-[47px] text-[16px] font-bold leading-[20px]">{{ $item['label'] }}</span>
</a>
