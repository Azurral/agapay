<x-layouts.guest title="Privacy Notice · Agapay">
    <header class="mx-auto flex h-[84px] max-w-[860px] items-center justify-between px-[24px]">
        <a href="{{ route('home') }}" class="flex items-center gap-[12px]">
            <x-logo class="block size-[40px]" />
            <span class="text-[22px] font-bold leading-[28px]">Agapay</span>
        </a>
        <a href="{{ route('login') }}" class="bg-brand-login gradient-button flex h-[42px] items-center rounded-[100px] px-[26px] text-[14px] font-bold text-white">
            Log in
        </a>
    </header>

    <main class="mx-auto max-w-[860px] px-[24px] pt-[24px] pb-[64px]">
        <article class="rounded-[24px] border border-muted bg-white px-[34px] pt-[30px] pb-[34px] text-[15px] leading-[24px]">
            <h1 class="text-[30px] font-bold leading-[38px]">Privacy Notice</h1>
            <p class="mt-[6px] text-[13px] text-arrow">Office of the Municipal Agriculturist (OMAG), Bontoc, Mountain Province</p>

            <p class="mt-[18px]">{{ config('agapay.privacy_notice') }}</p>

            @foreach ([
                'What we keep' => 'The farmer\'s name, birthdate, address and barangay, contact number, RSBSA number, farm area and crops, the programs and assistance they received, their assistance requests, and crisis damage reports with photos.',
                'Why we keep it' => 'To register farmers, decide and record who receives DA and LGU programs, avoid giving the same assistance twice to one household, report crop damage after a crisis, and prepare the reports OMAG submits to the Municipality and the Department of Agriculture.',
                'Who can see it' => 'Only OMAG staff with an AGAPAY account, and each person only sees what their role allows. Every addition, change and download is recorded in the audit trail with the staff member\'s name and the time. Exported lists leave out birthdates and contact numbers.',
                'Where it is kept' => 'On the OMAG office computer. Daily backups are kept in the same office. The information is not sold or shared with anyone outside OMAG, except in the reports required by the Municipality and the Department of Agriculture.',
                'How long it is kept' => 'For as long as the farmer is registered with OMAG and as required by government record-keeping rules. Records that are no longer needed are archived.',
                'Your rights' => 'Under the Data Privacy Act, every farmer may ask to see the information OMAG keeps about them, have it corrected, or object to its use. Visit or contact the Office of the Municipal Agriculturist, Bontoc, to make a request.',
            ] as $heading => $text)
                <h2 class="mt-[22px] text-[17px] font-bold leading-[24px] text-brand">{{ $heading }}</h2>
                <p class="mt-[4px]">{{ $text }}</p>
            @endforeach
        </article>
    </main>

    <footer class="pb-[28px] text-center text-[12px] text-arrow">
        Office of the Municipal Agriculturist · Bontoc, Mountain Province · AGAPAY v{{ config('agapay.version') }}
    </footer>
</x-layouts.guest>
