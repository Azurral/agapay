@props(['stat'])
<div class="flex h-[51px] flex-col">
    <dd class="text-[24px] font-bold leading-[31px]" style="color: {{ $stat['color'] }}">{{ number_format($stat['value']) }}</dd>
    <dt class="flex items-center gap-[6px] pl-[1px] text-[16px] leading-[20px] text-black">
        <span class="size-[11px] shrink-0 rounded-[3px]" style="background: {{ $stat['color'] }}"></span>
        {{ $stat['label'] }}
    </dt>
</div>
