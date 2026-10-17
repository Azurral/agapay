@php
    $box = 'flex h-[44px] items-center rounded-[10px] border bg-white px-[16px] text-[14px] font-bold leading-[20px]';
    $fields = [
        ['current_password', 'Current Password', 'current-password'],
        ['password', 'New Password', 'new-password'],
        ['password_confirmation', 'Type the New Password Again', 'new-password'],
    ];
@endphp
<x-layouts.app title="CHANGE PASSWORD">
    @if ($forced)
        <p class="flash rounded-[10px] border-[1.5px] border-bad bg-white px-[16px] py-[10px] text-[14px] font-bold" role="alert">
            Please choose your own password before you continue. The administrator set the one you used to sign in.
        </p>
    @endif

    <section class="max-w-[620px] rounded-[20px] border-[1.5px] border-black/10 bg-white pt-[19.5px] pr-[26.5px] pb-[22px] pl-[21.5px]">
        <h2 class="text-[16px] font-bold leading-[20px]">Change Password</h2>
        <p class="mt-[2px] text-[13px] text-muted">At least 8 characters, different from your current password. Only you will know it.</p>
        <div class="divider mt-[10px]"></div>

        <form method="POST" action="{{ route('password.update') }}" class="mt-[14px] flex flex-col gap-[12px]" x-data="{ show: false, pw: '', pw2: '' }">
            @csrf
            @method('PUT')
            @foreach ($fields as [$name, $label, $autocomplete])
                <div>
                    <label class="{{ $box }} {{ $errors->has($name) ? 'border-bad' : 'border-field' }}">
                        <span class="w-[220px] shrink-0">{{ $label }}:</span>
                        <input name="{{ $name }}" type="password" :type="show ? 'text' : 'password'" autocomplete="{{ $autocomplete }}" required
                               @if ($loop->first) autofocus @endif
                               @if ($name === 'password') x-model="pw" @elseif ($name === 'password_confirmation') x-model="pw2" @endif
                               class="h-full min-w-0 flex-1 bg-transparent font-bold outline-none">
                    </label>
                    @error($name)<p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <x-ui.password-guide />
            <label class="flex w-fit cursor-pointer items-center gap-[8px] pl-[4px] text-[13px] font-medium text-muted">
                <input type="checkbox" x-model="show" class="size-[16px] cursor-pointer accent-brand"> Show passwords
            </label>
            <button type="submit" :disabled="pw.length < 8 || pw !== pw2" class="disabled:cursor-not-allowed disabled:opacity-50 bg-brand-bar gradient-button mt-[6px] h-[47px] w-full rounded-[50px] text-[20px] font-bold leading-[24px] text-white">Save New Password</button>
        </form>
    </section>
</x-layouts.app>
