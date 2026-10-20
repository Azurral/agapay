{{-- The Data Privacy Act consent confirmation (RA 10173), required before a farmer's information is saved. --}}
@props(['text', 'error' => null])
<div {{ $attributes }}>
    <label @class([
        'flex cursor-pointer items-start gap-[12px] rounded-[15px] border-[1.5px] bg-white px-[18px] py-[13px] text-[14px] font-semibold leading-[21px]',
        'border-bad' => $error,
        'border-black/10' => ! $error,
    ])>
        <input type="checkbox" name="consent" value="1" required @checked(old('consent'))
               class="mt-[2px] size-[18px] shrink-0 cursor-pointer accent-brand">
        <span>{{ $text }} <a href="{{ route('privacy') }}" target="_blank" class="font-bold text-brand underline">Read the privacy notice</a></span>
    </label>
    @if ($error)
        <p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $error }}</p>
    @endif
</div>
