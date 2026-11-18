<x-layouts.app title="CLAIMING">
    @if (session('status'))
        <p class="flash mb-[14px] rounded-[10px] border-[1.5px] border-ok bg-white px-[16px] py-[10px] text-[14px] font-bold" role="status" data-autohide>{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p class="mb-[14px] rounded-[10px] border-[1.5px] border-bad bg-white px-[16px] py-[10px] text-[14px] font-bold text-danger" role="alert">{{ $errors->first() }}</p>
    @endif

    <x-ui.card :title="$cycle->label.' · Claiming'">
        <x-slot:actions>
            <a href="{{ route('claiming.index', ['month' => $cycle->schedule_date?->format('Y-m')]) }}" class="text-[14px] font-bold text-brand hover:underline">&larr; Claiming Calendar</a>
        </x-slot:actions>
        <p class="mt-[8px] text-[14px]"><b>Claiming:</b> {{ $cycle->claimingLabel() ?: 'no date yet' }}
            @if ($cycle->venue) &middot; <b>Venue:</b> {{ $cycle->venue }} @endif
            &middot; <span class="font-bold {{ $cycle->claimingEnded() ? 'text-arrow' : 'text-brand' }}">{{ $cycle->claimingEnded() ? 'The claiming period is over.' : 'Claiming is upcoming or open.' }}</span></p>
        <p class="mt-[4px] text-[13px] text-arrow">On the claiming day, each farmer signs (or thumbmarks) the printed acknowledgment sheet when receiving; the signed sheet is then scanned and uploaded here as proof.</p>

        <div class="divider mt-[12px]"></div>
        @forelse ($programs as $p)
            <div class="grid grid-cols-[1fr_120px_120px_200px_330px] items-center gap-[10px] border-b border-black/5 py-[10px] text-[14px]">
                <span class="font-bold">{{ strtoupper($p['intervention']->source) }} - {{ $p['intervention']->name }}</span>
                <span>{{ $p['claimed'] }} claimed</span>
                <span>{{ $p['to_claim'] }} to claim</span>
                @can('reports.generate')
                    <a href="{{ route('claiming.sheet', [$cycle, 'intervention' => $p['intervention']->id]) }}" target="_blank"
                       class="border-gradient pill-button rounded-[50px] py-[6px] text-center text-[13px] font-bold">Print acknowledgment sheet</a>
                @else
                    <span></span>
                @endcan
                @can('interventions.claim')
                    <form method="POST" action="{{ route('claiming.signed', $cycle) }}" enctype="multipart/form-data" class="flex items-center gap-[6px]">
                        @csrf
                        <input type="hidden" name="intervention_id" value="{{ $p['intervention']->id }}">
                        <input type="file" name="file" accept="application/pdf,image/jpeg,image/png" required aria-label="Signed sheet for {{ $p['intervention']->name }}"
                               class="min-w-0 flex-1 text-[12px] file:mr-[6px] file:rounded-full file:border-0 file:bg-[#f7f7f9] file:px-[10px] file:py-[4px] file:text-[12px] file:font-bold">
                        <button type="submit" class="bg-brand-button gradient-button rounded-[50px] px-[12px] py-[6px] text-[12px] font-bold text-white">Upload signed</button>
                    </form>
                @endcan
            </div>
        @empty
            <p class="py-[12px] text-[14px] font-bold">No program records in this cycle yet.</p>
        @endforelse
    </x-ui.card>

    @if ($cycle->claimingEnded())
        <x-ui.card title="For verification: unclaimed after the claiming period" class="mt-[20px]">
            <p class="mt-[8px] text-[13px] text-arrow">These farmers did not claim by {{ $cycle->claimEnd()?->format('M j, Y') }}. Verify each one:
                forward it to the next claiming date{{ $next ? ' ('.$next->code.', '.$next->claimingLabel().')' : '' }}, or close it with the reason.</p>
            <div class="divider mt-[12px]"></div>
            @forelse ($toVerify as $record)
                <div class="grid grid-cols-[230px_1fr_120px_400px] items-center gap-[10px] border-b border-black/5 py-[10px] text-[14px]" x-data="{ closing: false }">
                    <a href="{{ route('beneficiaries.show', $record->beneficiary) }}" class="font-bold hover:text-brand">{{ $record->beneficiary->fullName() }}</a>
                    <span>{{ strtoupper($record->intervention->source) }} - {{ $record->intervention->name }} &middot; {{ $record->beneficiary->barangay?->name }}</span>
                    <span class="text-arrow">{{ $record->quantityDisplay() }}</span>
                    @can('interventions.validate')
                        <div class="flex flex-col gap-[6px]">
                            <div class="flex gap-[6px]">
                                <form method="POST" action="{{ route('claiming.forward', $record) }}" class="flex-1">
                                    @csrf
                                    <button type="submit" @disabled(! $next) title="{{ $next ? '' : 'Add the next distribution cycle first' }}"
                                            class="bg-brand-button gradient-button h-[34px] w-full rounded-[50px] text-[13px] font-bold text-white disabled:opacity-50">Forward{{ $next ? ' to '.$next->code : '' }}</button>
                                </form>
                                <button type="button" @click="closing = ! closing" class="border-gradient pill-button h-[34px] flex-1 rounded-[50px] text-[13px] font-bold">Close, not forwarded</button>
                            </div>
                            <form method="POST" action="{{ route('claiming.close', $record) }}" x-show="closing" x-cloak class="flex gap-[6px]">
                                @csrf
                                <input name="reason" required maxlength="500" placeholder="Reason, e.g. moved away (barangay confirmed)"
                                       class="h-[34px] min-w-0 flex-1 rounded-[50px] border-[1.5px] border-black/15 px-[12px] text-[13px] outline-none focus:border-brand-soft">
                                <button type="submit" class="h-[34px] rounded-[50px] bg-danger px-[12px] text-[12px] font-bold text-white">Confirm</button>
                            </form>
                        </div>
                    @else
                        <span class="text-[13px] text-arrow">Waiting for the Agricultural Technologist's verification</span>
                    @endcan
                </div>
            @empty
                <p class="py-[12px] text-[14px] font-bold">Every record was claimed or has been verified.</p>
            @endforelse

            @if ($decided->isNotEmpty())
                <p class="mt-[14px] text-[13px] font-bold">Already verified</p>
                @foreach ($decided as $record)
                    <p class="text-[13px]">{{ $record->beneficiary->fullName() }} &middot; {{ $record->intervention->name }} &middot;
                        <b>{{ $record->forward_status === \App\Models\InterventionRecord::FORWARDED ? 'Forwarded' : 'Closed' }}</b>: {{ $record->forward_note }}</p>
                @endforeach
            @endif
        </x-ui.card>
    @endif

    <x-ui.card title="Signed acknowledgment sheets (proof of release)" class="mt-[20px]">
        <div class="divider mt-[12px]"></div>
        @forelse ($sheets as $sheet)
            <div class="grid grid-cols-[1fr_300px_220px] items-center gap-[10px] border-b border-black/5 py-[8px] text-[14px]">
                <a href="{{ route('claiming.signed.download', $sheet) }}" target="_blank" class="font-bold text-brand hover:underline">{{ $sheet->original_name }}</a>
                <span>{{ strtoupper($sheet->intervention->source) }} - {{ $sheet->intervention->name }}</span>
                <span class="text-arrow">{{ $sheet->uploader?->username }} &middot; {{ $sheet->created_at->format('M j, Y') }}</span>
            </div>
        @empty
            <p class="py-[12px] text-[14px]">No signed sheet uploaded yet.</p>
        @endforelse
    </x-ui.card>
</x-layouts.app>
