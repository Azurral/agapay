@props(['title'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ \Illuminate\Support\Str::title(strtolower($title)) }} · Agapay</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <x-screen-fit />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- "hiding": after the panel closes on a scrolled page, the pinned header slides up out of view before it unpins. --}}
<body x-data="{ nav: false, hiding: false }"
      x-init="$watch('nav', open => { if (! open && window.scrollY > 0) { hiding = true; setTimeout(() => hiding = false, 220); } })" class="min-h-[var(--app-vh,100vh)] min-w-[1280px] bg-white font-sans text-ink antialiased">
    <x-desktop-only />
    <x-app.header :title="$title" />

    <x-app.sidebar />

    <div class="flex">
        <main class="bg-grid relative mt-[11px] min-h-[calc(var(--app-vh,100vh)-81px)] flex-1 overflow-hidden rounded-t-[20px] border-t-[1.5px] border-black/15 pt-[22px] pr-[37px] pb-[40px] pl-[36px]">
            <x-app.greeting />
            @can('beneficiaries.view')
                <x-app.search-bar class="mt-[34px]" />
            @endcan

            <div class="mt-[28px] flex flex-col gap-[14px]">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
