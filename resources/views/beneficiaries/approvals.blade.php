<x-layouts.app title="WALK-IN APPROVALS">
    @if (session('status'))
        <p class="flash mb-[14px] rounded-[10px] border-[1.5px] border-ok bg-white px-[16px] py-[10px] text-[14px] font-bold" role="status" data-autohide>{{ session('status') }}</p>
    @endif

    {{-- No ghost farmers: each hand-entered registration waits here until the Administrator checks the valid ID. --}}
    <x-ui.card title="For Approval">
        <p class="mt-[8px] text-[13px] text-arrow">Farmers added by hand (walk-in registrations). Compare the ID photo with the name and birthdate,
            then approve, or reject with the reason. Until approved, a farmer cannot be put on any program. Farmers from the DA masterlist (Excel import) are already verified.</p>
        <div class="divider mt-[12px]"></div>

        @forelse ($pending as $farmer)
            <div class="grid grid-cols-[150px_1fr_330px] items-start gap-[18px] border-b border-black/5 py-[14px]" x-data="{ rejecting: false }">
                <div class="flex flex-col gap-[8px]">
                    <a href="{{ route('beneficiaries.valid-id', $farmer) }}" target="_blank" title="Open the valid ID photo">
                        <img src="{{ route('beneficiaries.valid-id', $farmer) }}" alt="Valid ID of {{ $farmer->fullName() }}"
                             class="h-[95px] w-[150px] rounded-[10px] border border-black/10 bg-[#f7f7f9] object-cover">
                    </a>
                    @if ($farmer->rsbsa_id_photo)
                        <a href="{{ route('beneficiaries.rsbsa-id', $farmer) }}" target="_blank" title="Open the RSBSA / agricultural ID photo">
                            <img src="{{ route('beneficiaries.rsbsa-id', $farmer) }}" alt="RSBSA ID of {{ $farmer->fullName() }}"
                                 class="h-[95px] w-[150px] rounded-[10px] border border-black/10 bg-[#f7f7f9] object-cover">
                        </a>
                    @endif
                </div>
                <div class="text-[14px] leading-[21px]">
                    <a href="{{ route('beneficiaries.show', $farmer) }}" class="text-[16px] font-bold hover:text-brand">{{ $farmer->fullName() }}</a>
                    <p>Born {{ $farmer->birthdate?->format('M j, Y') }} &middot; {{ $farmer->fullAddress() }}</p>
                    <p><b>{{ $farmer->validIdLabel() ?? 'No ID type' }}</b> &middot; No. {{ $farmer->valid_id_number }}</p>
                    @if ($farmer->rsbsa_number)
                        <p><b>RSBSA / agricultural ID</b> &middot; No. {{ $farmer->rsbsa_number }}</p>
                    @endif
                    <p class="text-[12px] text-arrow">Added by {{ $farmer->creator?->username ?? 'unknown' }} on {{ $farmer->created_at->format('M j, Y - g:i A') }}</p>
                    @if ($farmer->id_checks)
                        {{-- What AGAPAY checked by itself; whether the ID and signature are genuine is the Administrator's call. --}}
                        <div class="mt-[6px] rounded-[10px] bg-tint px-[10px] py-[6px] text-[12px] leading-[18px]">
                            <p class="font-bold">Automatic checks
                                @if ($farmer->failedIdChecks() > 0)
                                    <span class="text-danger">&middot; {{ $farmer->failedIdChecks() }} {{ \Illuminate\Support\Str::plural('check', $farmer->failedIdChecks()) }} failed</span>
                                @endif
                            </p>
                            @foreach ($farmer->id_checks as $check)
                                <p @class(['text-danger font-semibold' => $check['result'] === 'fail', 'text-muted' => $check['result'] === 'unknown'])>
                                    {{ ['pass' => '✓', 'fail' => '✗', 'unknown' => '?'][$check['result']] ?? '?' }} {{ $check['check'] }}
                                </p>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="flex flex-col gap-[8px]">
                    <div class="flex gap-[8px]">
                        <form method="POST" action="{{ route('beneficiaries.approve', $farmer) }}" class="flex-1">
                            @csrf
                            <button type="submit" class="bg-brand-button gradient-button h-[38px] w-full rounded-[50px] text-[14px] font-bold text-white">Approve</button>
                        </form>
                        <button type="button" @click="rejecting = ! rejecting"
                                class="border-gradient pill-button h-[38px] flex-1 rounded-[50px] text-[14px] font-bold">Reject</button>
                    </div>
                    <form method="POST" action="{{ route('beneficiaries.reject', $farmer) }}" x-show="rejecting" x-cloak class="flex gap-[8px]">
                        @csrf
                        <input name="reason" required maxlength="500" placeholder="Reason, e.g. ID does not match the name"
                               class="h-[38px] min-w-0 flex-1 rounded-[50px] border-[1.5px] border-black/15 px-[14px] text-[13px] outline-none focus:border-brand-soft">
                        <button type="submit" class="h-[38px] rounded-[50px] bg-danger px-[14px] text-[13px] font-bold text-white">Confirm</button>
                    </form>
                    @error('reason') <p class="text-[12px] font-bold text-danger">{{ $message }}</p> @enderror
                </div>
            </div>
        @empty
            <p class="py-[14px] text-[14px] font-bold">No walk-in registrations are waiting for approval.</p>
        @endforelse
    </x-ui.card>

    {{-- Proof of eligibility: a validation status change sent by an Agricultural Technologist waits here with its proof. --}}
    <x-ui.card title="Status Changes for Approval" class="mt-[20px]">
        <p class="mt-[8px] text-[13px] text-arrow">Open the proof (a signed certification or validation sheet) and compare it with the farmer,
            then approve the change, or reject it with the reason. Until approved, the record keeps its current status.</p>
        @error('proof') <p class="mt-[6px] text-[12px] font-bold text-danger">{{ $message }}</p> @enderror
        <div class="divider mt-[12px]"></div>
        @forelse ($proofs as $proof)
            <div class="grid grid-cols-[150px_1fr_330px] items-start gap-[18px] border-b border-black/5 py-[14px]" x-data="{ rejecting: false }">
                <a href="{{ route('validation-proofs.file', $proof) }}" target="_blank" title="Open the proof"
                   class="flex h-[95px] w-[150px] items-center justify-center overflow-hidden rounded-[10px] border border-black/10 bg-[#f7f7f9] text-[13px] font-bold text-brand">
                    @if (str_ends_with(strtolower($proof->original_name), '.pdf'))
                        Open PDF
                    @else
                        <img src="{{ route('validation-proofs.file', $proof) }}" alt="Proof for {{ $proof->record->beneficiary->fullName() }}" class="h-full w-full object-cover">
                    @endif
                </a>
                <div class="text-[14px] leading-[21px]">
                    <a href="{{ route('beneficiaries.show', $proof->record->beneficiary) }}" class="text-[16px] font-bold hover:text-brand">{{ $proof->record->beneficiary->fullName() }}</a>
                    <p>{{ $proof->record->intervention->sourcedName() }} ({{ $proof->record->cycle?->code }})</p>
                    <p><b>{{ \App\Models\InterventionRecord::validationLabel($proof->from_status) }}</b> &rarr; <b>{{ \App\Models\InterventionRecord::validationLabel($proof->to_status) }}</b></p>
                    <p class="text-[12px] text-arrow">Sent by {{ $proof->submitter?->username ?? 'unknown' }} on {{ $proof->created_at->format('M j, Y - g:i A') }} &middot; {{ $proof->original_name }}</p>
                    <div class="mt-[6px] rounded-[10px] bg-tint px-[10px] py-[6px] text-[12px] leading-[18px]">
                        <p class="font-bold">Automatic checks
                            @if ($proof->failedChecks() > 0)
                                <span class="text-danger">&middot; {{ $proof->failedChecks() }} {{ \Illuminate\Support\Str::plural('check', $proof->failedChecks()) }} failed</span>
                            @endif
                        </p>
                        @foreach ($proof->checks ?? [] as $check)
                            <p @class(['text-danger font-semibold' => $check['result'] === 'fail', 'text-muted' => $check['result'] === 'unknown'])>
                                {{ ['pass' => '✓', 'fail' => '✗', 'unknown' => '?'][$check['result']] ?? '?' }} {{ $check['check'] }}
                            </p>
                        @endforeach
                    </div>
                </div>
                <div class="flex flex-col gap-[8px]">
                    <div class="flex gap-[8px]">
                        <form method="POST" action="{{ route('validation-proofs.approve', $proof) }}" class="flex-1">
                            @csrf
                            <button type="submit" class="bg-brand-button gradient-button h-[38px] w-full rounded-[50px] text-[14px] font-bold text-white">Approve</button>
                        </form>
                        <button type="button" @click="rejecting = ! rejecting"
                                class="border-gradient pill-button h-[38px] flex-1 rounded-[50px] text-[14px] font-bold">Reject</button>
                    </div>
                    <form method="POST" action="{{ route('validation-proofs.reject', $proof) }}" x-show="rejecting" x-cloak class="flex gap-[8px]">
                        @csrf
                        <input name="reason" required maxlength="500" placeholder="Reason, e.g. certification not signed"
                               class="h-[38px] min-w-0 flex-1 rounded-[50px] border-[1.5px] border-black/15 px-[14px] text-[13px] outline-none focus:border-brand-soft">
                        <button type="submit" class="h-[38px] rounded-[50px] bg-danger px-[14px] text-[13px] font-bold text-white">Confirm</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="py-[14px] text-[14px] font-bold">No status changes are waiting for approval.</p>
        @endforelse
    </x-ui.card>

    @if ($rejected->isNotEmpty())
        <x-ui.card title="Recently Rejected" class="mt-[20px]">
            <div class="divider mt-[12px]"></div>
            @foreach ($rejected as $farmer)
                <div class="grid grid-cols-[260px_1fr_220px_110px] items-center gap-[12px] border-b border-black/5 py-[10px] text-[14px]">
                    <a href="{{ route('beneficiaries.show', $farmer) }}" class="font-bold hover:text-brand">{{ $farmer->fullName() }}</a>
                    <span>{{ $farmer->rejection_reason }}</span>
                    <span class="text-arrow">{{ $farmer->verifier?->username }} &middot; {{ $farmer->verified_at?->format('M j, Y') }}</span>
                    <form method="POST" action="{{ route('beneficiaries.approve', $farmer) }}">
                        @csrf
                        <button type="submit" class="border-gradient pill-button h-[32px] w-full rounded-[50px] text-[13px] font-bold">Approve</button>
                    </form>
                </div>
            @endforeach
        </x-ui.card>
    @endif
</x-layouts.app>
