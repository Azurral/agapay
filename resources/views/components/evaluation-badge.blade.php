@props(['accounts' => false])
{{-- Evaluation copy for the panel (config agapay.evaluation): every page says the records are samples, and the sign-in
     page lists the test accounts of the black-box test cases. Never shown on OMAG's own copy. --}}
@if (config('agapay.evaluation'))
    <div class="pointer-events-none fixed right-[16px] bottom-[16px] z-[60] flex flex-col items-end gap-[8px] print:hidden">
        @if ($accounts)
            <div class="pointer-events-auto rounded-[10px] border border-black/15 bg-white/95 px-[14px] py-[10px] text-[13px] leading-[1.5] shadow-md">
                <p class="font-semibold">Test accounts (password {{ config('agapay.seed_password') }})</p>
                <p>Administrator: <span class="font-mono">Admin_01</span></p>
                <p>Agricultural Technologist: <span class="font-mono">Agritech_02</span></p>
                <p>Data Encoder: <span class="font-mono">Encoder_03</span></p>
                <p class="text-black/60">Inactive (test case BB-04): <span class="font-mono">Encoder_04</span></p>
            </div>
        @endif
        <p class="rounded-full bg-[#b45309] px-[12px] py-[5px] text-[12px] font-semibold text-white shadow-md">Sample records only</p>
    </div>
@endif
