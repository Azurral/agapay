<x-layouts.guest title="Login · Agapay">
    <main class="flex min-h-screen items-center justify-center">
        <div class="flex flex-col items-center">
            <div class="flex items-center gap-[14px]">
                <img src="{{ asset('images/logo.svg') }}" alt="Agapay logo" class="block size-[84px]">
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
                <div x-data="{ show: false }" class="relative mt-[2px] w-[320px]">
                    <input id="password" name="password" type="password" :type="show ? 'text' : 'password'" autocomplete="current-password" required
                           class="border-gradient h-[40px] w-[320px] rounded-[100px] pr-[48px] pl-[18px] text-[14px] font-medium outline-none">
                    <button type="button" @click="show = ! show" :aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password"
                            :aria-pressed="show.toString()"
                            class="absolute top-[6px] right-[10px] flex size-[28px] items-center justify-center rounded-full text-muted hover:text-brand">
                        <svg x-show="! show" class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg x-cloak x-show="show" class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 3l18 18M10.6 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.2M6.6 6.6A17.4 17.4 0 0 0 2 12s3.5 7 10 7a10.4 10.4 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                        </svg>
                    </button>
                </div>

                <p class="mt-[9px] h-[20px] text-center text-[12px] font-semibold leading-[20px] text-danger" role="alert">
                    {{ $errors->first('username') ?: $errors->first('password') }}
                </p>

                <button type="submit"
                        class="bg-brand-login gradient-button mt-[9px] h-[44px] w-[320px] rounded-[100px] text-[14px] font-bold text-white">
                    LOGIN
                </button>
            </form>
        </div>
    </main>
</x-layouts.guest>
