@php
    $user = auth()->user();
    $items = \App\Support\Navigation::items($user);
@endphp
{{-- Off-canvas side panel: hidden until the header's logo button opens it (the layout owns "nav"). It lines up with the
     grid container's top-left corner, and its contents are drawn 15% smaller like the container's. --}}
<div x-cloak x-show="nav" x-transition.opacity class="fixed inset-0 top-[70px] z-30 bg-black/20" @click="nav = false"></div>
<aside id="side-panel" x-cloak x-show="nav"
       x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
       @keydown.escape.window="nav = false"
       class="fixed top-[81px] left-[10px] z-40 h-[calc(var(--app-vh,100vh)-81px)] w-[224px] overflow-y-auto rounded-tl-[20px] rounded-tr-[20px] border-t-[1.5px] border-r-[1.5px] border-l-[1.5px] border-black/15 bg-white shadow-[4px_0_16px_rgba(0,0,0,0.08)]">
    <div class="[zoom:0.85] flex h-full flex-col pb-[16px]">
    <div class="relative z-30 mt-[10px] pl-[12px]">
        <x-app.account-menu :user="$user" />
    </div>

    <nav class="mt-[12px] flex flex-col gap-[2px] pl-[12px]" aria-label="Main">
        @foreach ($items as $item)
            <x-app.nav-item :$item />
        @endforeach
    </nav>

    <div class="mt-auto flex shrink-0 flex-col gap-[8px] pt-[16px] pl-[24px]">
        <x-app.theme-card />
        <x-app.return-card />
    </div>
    </div>
</aside>
