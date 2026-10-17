@php($release = auth()->user()?->can('users.manage') ? app(\App\Support\UpdateChecker::class)->available() : null)
@if ($release)
    {{-- Office copy only: a newer AGAPAY is on GitHub. Styled like the Return to Login card; the Administrator updates. --}}
    <div x-data="{ notes: false }" class="bg-brand-card relative w-[211px] rounded-[15px] px-[14px] pt-[10px] pb-[12px] text-white">
        <p class="text-[15px] font-bold leading-[18px]">UPDATE AVAILABLE</p>
        <p class="text-[12px] leading-[16px]">v{{ $release['version'] }} is ready ·
            <button type="button" @click="notes = ! notes" class="underline hover:no-underline">What's new</button></p>
        <p x-cloak x-show="notes" class="mt-[6px] max-h-[120px] overflow-y-auto whitespace-pre-line rounded-[8px] bg-white/15 px-[8px] py-[6px] text-[11px] leading-[15px]">{{ $release['notes'] ?: 'Improvements and fixes.' }}</p>
        <form method="POST" action="{{ route('system.update') }}" class="mt-[8px]"
              onsubmit="return confirm('Update AGAPAY to v{{ $release['version'] }}?\n\nA backup is made first, and AGAPAY restarts for about a minute. Ask everyone to save their work.')">
            @csrf
            <button type="submit" class="invert-button h-[28px] w-full rounded-[50px] bg-white text-[14px] font-bold text-ink">Update</button>
        </form>
    </div>
@endif
