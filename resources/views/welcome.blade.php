@php
    $features = [
        ['title' => 'Beneficiary Records', 'text' => 'One list of Bontoc\'s farmers, their households, farm areas and RSBSA numbers, with duplicate checks.'],
        ['title' => 'DA & LGU Programs', 'text' => 'Programs per distribution cycle, the one-per-household rule, and stock deducted as items are released.'],
        ['title' => 'Crisis & Damage Reports', 'text' => 'Crop damage after typhoons and floods, with photos, location and computed loss and cost.'],
        ['title' => 'Reports & Audit Trail', 'text' => 'PDF and Excel reports in one click, and a record of who changed what and when.'],
    ];
@endphp
<x-layouts.guest title="Agapay · OMAG Bontoc">
    <header class="mx-auto flex h-[84px] max-w-[1120px] items-center justify-between px-[24px]">
        <div class="flex items-center gap-[12px]">
            <img src="{{ asset('images/logo.svg') }}" alt="Agapay logo" class="block size-[40px]">
            <span class="text-[22px] font-bold leading-[28px]">Agapay</span>
        </div>
        <a href="{{ route('login') }}" class="bg-brand-login gradient-button flex h-[42px] items-center rounded-[100px] px-[26px] text-[14px] font-bold text-white">
            Log in
        </a>
    </header>

    <main class="mx-auto max-w-[1120px] px-[24px] pt-[56px] pb-[72px]">
        <section class="flex flex-col items-center text-center">
            <img src="{{ asset('images/logo.svg') }}" alt="" class="block size-[96px]">
            <h1 class="mt-[18px] text-[56px] font-bold leading-[64px]">Agapay</h1>
            <p class="mt-[12px] max-w-[640px] text-[18px] leading-[28px]">
                Centralized agricultural beneficiary and intervention management for the
                Office of the Municipal Agriculturist, Bontoc, Mountain Province.
            </p>
            <a href="{{ route('login') }}" class="bg-brand-login gradient-button mt-[30px] flex h-[48px] items-center rounded-[100px] px-[40px] text-[16px] font-bold text-white">
                Log in to Agapay
            </a>
            <p class="mt-[12px] text-[13px] text-arrow">For OMAG staff accounts only.</p>
        </section>

        <section class="mt-[64px] grid grid-cols-1 gap-[18px] sm:grid-cols-2 lg:grid-cols-4" aria-label="What Agapay does">
            @foreach ($features as $feature)
                <article class="rounded-[24px] border border-muted bg-white px-[22px] pt-[22px] pb-[26px]">
                    <h2 class="text-[16px] font-bold leading-[22px] text-brand">{{ $feature['title'] }}</h2>
                    <p class="mt-[8px] text-[14px] leading-[21px]">{{ $feature['text'] }}</p>
                </article>
            @endforeach
        </section>
    </main>

    <footer class="pb-[28px] text-center text-[12px] text-arrow">
        Office of the Municipal Agriculturist · Bontoc, Mountain Province
    </footer>
</x-layouts.guest>
