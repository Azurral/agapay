{{-- Shown only on phones and tablets (touch screens without a mouse); the page behind it is blurred (app.css). --}}
<div class="desktop-only-notice" role="alertdialog" aria-modal="true" aria-labelledby="desktop-only-title" aria-describedby="desktop-only-text">
    <div class="mx-[20px] flex max-w-[420px] flex-col items-center rounded-[24px] border border-muted bg-white px-[28px] pt-[28px] pb-[30px] text-center shadow-[0_20px_50px_rgba(90,93,227,0.25)]">
        <img src="{{ asset('images/logo.svg') }}" alt="Agapay logo" class="block size-[64px]">
        <h2 id="desktop-only-title" class="mt-[14px] text-[22px] font-bold leading-[28px]">Please use a desktop or laptop</h2>
        <p id="desktop-only-text" class="mt-[10px] text-[15px] leading-[22px]">
            AGAPAY is made for desktop and laptop browsers (Chrome, Edge or Firefox).
            Open <b class="break-all text-brand">{{ url('/') }}</b> on a computer to continue.
        </p>
        <p class="mt-[12px] text-[12px] text-arrow">Office of the Municipal Agriculturist · Bontoc</p>
    </div>
</div>
