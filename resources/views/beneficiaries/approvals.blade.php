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
                <a href="{{ route('beneficiaries.valid-id', $farmer) }}" target="_blank" title="Open the ID photo">
                    <img src="{{ route('beneficiaries.valid-id', $farmer) }}" alt="Valid ID of {{ $farmer->fullName() }}"
                         class="h-[95px] w-[150px] rounded-[10px] border border-black/10 bg-[#f7f7f9] object-cover">
                </a>
                <div class="text-[14px] leading-[21px]">
                    <a href="{{ route('beneficiaries.show', $farmer) }}" class="text-[16px] font-bold hover:text-brand">{{ $farmer->fullName() }}</a>
                    <p>Born {{ $farmer->birthdate?->format('M j, Y') }} &middot; {{ $farmer->fullAddress() }}</p>
                    <p><b>{{ $farmer->validIdLabel() ?? 'No ID type' }}</b> &middot; No. {{ $farmer->valid_id_number }}</p>
                    <p class="text-[12px] text-arrow">Added by {{ $farmer->creator?->username ?? 'unknown' }} on {{ $farmer->created_at->format('M j, Y - g:i A') }}</p>
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
