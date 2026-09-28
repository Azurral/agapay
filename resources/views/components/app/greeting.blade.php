@php($actions = \App\Support\Navigation::quickActions(auth()->user()))
<div>
    <h1 class="text-[32px] font-bold leading-[42px]">Hello, {{ auth()->user()->greetingName() }}</h1>
    <div class="flex items-start">
        {{-- Gradient text lives on the inline span so the gradient spans the text, not the 479px column. --}}
        <p class="w-[479px] shrink-0 text-[32px] font-bold leading-[42px]">
            <span class="text-gradient-heading">Select an action to get started:</span>
        </p>
        <div class="mt-[3px] flex flex-wrap" style="gap: {{ $actions['gap'] }}px">
            @foreach ($actions['items'] as $action)
                <a href="{{ $action['url'] }}"
                   class="border-gradient flex h-[39px] items-center justify-center rounded-[50px] text-[20px] font-bold leading-[24px] whitespace-nowrap"
                   style="width: {{ $actions['width'] }}px">{{ $action['label'] }}</a>
            @endforeach
        </div>
    </div>
</div>
