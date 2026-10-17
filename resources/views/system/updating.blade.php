<x-layouts.guest title="Updating · Agapay">
    {{-- AGAPAY stops while the new version is put in place; this page waits until it answers again, then reloads. --}}
    <main class="flex min-h-[var(--app-vh,100vh)] items-center justify-center"
          x-data="{ down: false, back: false, seconds: 0 }"
          x-init="setInterval(() => seconds++, 1000);
                  const poll = async () => {
                      try {
                          const r = await fetch(@js(url('/up')), { cache: 'no-store' });
                          if (r.ok && (down || seconds > 25)) { back = true; setTimeout(() => location.href = @js(route('dashboard')), 1200); return; }
                          if (! r.ok) down = true;
                      } catch (e) { down = true; }
                      setTimeout(poll, 2000);
                  };
                  setTimeout(poll, 3000);">
        <div class="flex w-[460px] flex-col items-center rounded-[30px] border border-muted bg-white px-[40px] py-[36px] text-center">
            <x-logo class="block size-[72px]" />
            <h1 class="mt-[14px] text-[24px] font-bold">Updating AGAPAY to v{{ $version }}</h1>
            <p class="mt-[8px] text-[14px] leading-[21px] text-muted" x-show="! back">
                A backup was made first. AGAPAY restarts in about a minute; keep the laptop on. This page opens AGAPAY again by itself.
            </p>
            <p class="mt-[8px] text-[14px] font-bold text-brand" x-cloak x-show="back">Done. Opening AGAPAY…</p>
            <span class="busy-spinner mt-[18px] text-brand" style="width: 28px; height: 28px; margin: 18px 0 0;" x-show="! back" aria-hidden="true"></span>
            <p class="mt-[14px] text-[12px] text-muted" x-text="seconds + ' s'"></p>
        </div>
    </main>
</x-layouts.guest>
