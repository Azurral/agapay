<x-layouts.guest title="Updating · Agapay">
    {{-- The old version keeps answering while the update downloads, so this page waits until AGAPAY reports the
         new version number (or the updater reports that it rolled back), then reloads. --}}
    <main class="flex min-h-[var(--app-vh,100vh)] items-center justify-center"
          x-data="{ back: false, failed: '', seconds: 0 }"
          x-init="const timer = setInterval(() => seconds++, 1000);
                  const poll = async () => {
                      try {
                          const r = await fetch(@js(route('system.update.status')), { cache: 'no-store', headers: { Accept: 'application/json' } });
                          const s = r.ok ? await r.json() : null;
                          if (s && s.version === @js($version)) { back = true; clearInterval(timer); setTimeout(() => location.href = @js(route('dashboard')), 1200); return; }
                          if (s && s.state === 'failed') { failed = s.message || 'AGAPAY was kept at the previous version.'; clearInterval(timer); return; }
                      } catch (e) { }
                      if (seconds > 900) { failed = 'It is taking too long. Restart the laptop; AGAPAY starts again by itself.'; clearInterval(timer); return; }
                      setTimeout(poll, 2000);
                  };
                  setTimeout(poll, 3000);">
        <div class="flex w-[460px] flex-col items-center rounded-[30px] border border-muted bg-white px-[40px] py-[36px] text-center">
            <x-logo class="block size-[72px]" />
            <h1 class="mt-[14px] text-[24px] font-bold">Updating AGAPAY to v{{ $version }}</h1>
            <p class="mt-[8px] text-[14px] leading-[21px] text-muted" x-show="! back && ! failed">
                AGAPAY downloads the update, makes a backup, then restarts. This takes a few minutes; keep the laptop on. This page opens AGAPAY again by itself.
            </p>
            <p class="mt-[8px] text-[14px] font-bold text-brand" x-cloak x-show="back">Done. Opening AGAPAY…</p>
            <div class="mt-[8px] text-[14px] leading-[21px]" x-cloak x-show="failed">
                <p class="font-bold text-danger">The update did not finish.</p>
                <p class="mt-[4px] text-muted" x-text="failed"></p>
                <p class="mt-[4px] text-muted">Your records are safe. <a class="font-bold text-brand underline" href="{{ route('dashboard') }}">Back to AGAPAY</a></p>
            </div>
            <span class="busy-spinner mt-[18px] text-brand" style="width: 28px; height: 28px; margin: 18px 0 0;" x-show="! back && ! failed" aria-hidden="true"></span>
            <p class="mt-[14px] text-[12px] text-muted" x-show="! failed" x-text="seconds + ' s'"></p>
        </div>
    </main>
</x-layouts.guest>
