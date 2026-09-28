<x-layouts.guest title="Login · Agapay">
    <main class="flex min-h-screen items-center justify-center">
        <div class="flex flex-col items-center">
            <div class="flex items-center gap-[14px]">
                <span class="logo-box block size-[84px] border-[5px]" style="--logo-fill: transparent"></span>
                <h1 class="text-[64px] font-bold leading-[83px]">Agapay</h1>
            </div>

            <form method="POST" action="{{ route('login.store') }}"
                  class="mt-[14px] flex w-[400px] flex-col items-center rounded-[30px] border border-muted bg-white px-[40px] pt-[30px] pb-[38px]">
                @csrf

                <label for="username" class="h-[20px] text-[12px] font-semibold leading-[20px]">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" required autofocus
                       value="{{ old('username', $username) }}"
                       class="border-gradient mt-[2px] h-[40px] w-[320px] rounded-[100px] px-[18px] text-[14px] font-medium outline-none">

                <label for="password" class="mt-[18px] h-[20px] text-[12px] font-semibold leading-[20px]">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="border-gradient mt-[2px] h-[40px] w-[320px] rounded-[100px] px-[18px] text-[14px] font-medium outline-none">

                <p class="mt-[9px] h-[20px] text-center text-[12px] font-semibold leading-[20px] text-danger" role="alert">
                    {{ $errors->first('username') ?: $errors->first('password') }}
                </p>

                <button type="submit"
                        class="bg-brand-login mt-[9px] h-[44px] w-[320px] rounded-[100px] text-[14px] font-bold text-white">
                    LOGIN
                </button>
            </form>
        </div>
    </main>
</x-layouts.guest>
