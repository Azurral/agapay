@props(['title'])
<section {{ $attributes->class('relative rounded-[20px] border-[1.5px] border-black/10 bg-white pt-[19.5px] pr-[26.5px] pb-[20px] pl-[21.5px]') }}>
    <header class="flex min-h-[20px] items-center justify-between gap-4">
        <div class="flex items-center gap-[5px]">
            <img src="{{ asset('images/figma/icons/task.svg') }}" alt="" class="size-[18px]">
            <h2 class="text-[16px] font-bold leading-[20px]">{{ $title }}</h2>
        </div>
        {{ $actions ?? '' }}
    </header>
    {{ $slot }}
</section>
