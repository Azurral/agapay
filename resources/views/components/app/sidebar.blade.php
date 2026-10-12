@php
    $user = auth()->user();
    $items = \App\Support\Navigation::items($user);
@endphp
{{-- Side panel: the header's logo button opens and closes it (the layout owns "nav", remembered per user). The grid
     container slides aside rather than being covered, so there is no dimmed overlay. It lines up with the container's
     top-left corner, and its contents are drawn 15% smaller like the container's. --}}
<aside id="side-panel" x-cloak x-show="nav"
       x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
       class="fixed top-[81px] left-0 z-40 h-[calc(var(--app-vh,100vh)-81px)] w-[224px] overflow-y-auto rounded-tr-[20px] border-t-[1.5px] border-r-[1.5px] border-black/15 bg-white shadow-[4px_0_16px_rgba(0,0,0,0.08)]">
    <div class="[zoom:0.85] flex h-full flex-col pb-[16px]">
    <div class="relative z-30 mt-[10px] pl-[12px]">
        <x-app.account-menu :user="$user" />
    </div>

    <nav class="mt-[12px] flex flex-col gap-[2px] pl-[12px]" aria-label="Main">
        @foreach ($items as $item)
            <x-app.nav-item :$item :index="$loop->index" />
        @endforeach
    </nav>

    <div class="mt-auto flex shrink-0 flex-col gap-[8px] pt-[16px] pl-[24px]">
        <x-app.theme-card />
        <x-app.return-card />
        <p class="text-center text-[11px] font-medium text-muted">AGAPAY v{{ config('agapay.version') }}</p>
    </div>
    </div>
</aside>
