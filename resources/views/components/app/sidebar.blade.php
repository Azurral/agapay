@php
    $user = auth()->user();
    $items = \App\Support\Navigation::items($user);
    $stats = \App\Support\Navigation::stats($user);
@endphp
<aside class="sticky top-0 flex h-[calc(100vh-70px)] w-[259px] shrink-0 flex-col overflow-y-auto pb-[28px]">
    <div class="relative z-30 mt-[10px] pl-[12px]">
        <x-app.account-menu :user="$user" />
    </div>

    <nav class="mt-[22px] flex flex-col gap-[4px] pl-[12px]" aria-label="Main">
        @foreach ($items as $item)
            <x-app.nav-item :$item />
        @endforeach
    </nav>

    <div class="divider mt-[12px] w-full"></div>

    <dl class="mt-[19px] flex flex-col pl-[27px]">
        @foreach ($stats as $stat)
            <x-app.stat :$stat />
        @endforeach
    </dl>

    <x-app.return-card class="mt-auto ml-[28px] shrink-0" />
</aside>
