@php
    $user = auth()->user();
    $items = \App\Support\Navigation::items($user);
@endphp
{{-- Off-canvas side panel: hidden until the header's hamburger opens it (the layout owns "nav"). --}}
<div x-cloak x-show="nav" x-transition.opacity class="fixed inset-0 top-[70px] z-30 bg-black/20" @click="nav = false"></div>
<aside id="side-panel" x-cloak x-show="nav"
       x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
       @keydown.escape.window="nav = false"
       class="fixed top-[70px] left-0 z-40 flex h-[calc(100vh-70px)] w-[259px] flex-col overflow-y-auto rounded-tr-[20px] border-r border-black/10 bg-white pb-[28px] shadow-[4px_0_16px_rgba(0,0,0,0.08)]">
    <div class="relative z-30 mt-[10px] pl-[12px]">
        <x-app.account-menu :user="$user" />
    </div>

    <nav class="mt-[22px] flex flex-col gap-[4px] pl-[12px]" aria-label="Main">
        @foreach ($items as $item)
            <x-app.nav-item :$item />
        @endforeach
    </nav>

    <x-app.return-card class="mt-auto ml-[28px] shrink-0 pt-[24px]" />
</aside>
