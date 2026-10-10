@props(['user'])
@php($others = \App\Support\KnownAccounts::others(request(), $user))
{{-- fold flips a frame after the menu shows, so its close arrow visibly folds from ▾ into an X (like the old hamburger). --}}
<div x-data="{ open: false, fold: false }" class="relative h-[56px] w-[235px]" @click.outside="open = false" @keydown.escape.window="open = false"
     x-init="$watch('open', v => v ? requestAnimationFrame(() => requestAnimationFrame(() => fold = true)) : fold = false)">
    <button type="button" @click="open = true" :aria-expanded="open" aria-haspopup="true"
            class="account-trigger group relative block h-[56px] w-[235px] rounded-[15px] border-[1.5px] border-black/15 bg-white text-left">
        <x-app.avatar :user="$user" class="absolute top-[8.5px] left-[9.5px]" />
        <span class="absolute top-[5px] left-[50px] text-[16px] font-bold leading-[20px]">{{ $user->username }}</span>
        <span class="absolute top-[25px] left-[50px] text-[13px] font-medium leading-[17px] text-subtle">{{ $user->roleShortName() }}</span>
        {{-- Same 26px hover circle and position as the menu's close arrow. --}}
        <span class="hover-tint absolute top-[14.5px] left-[200.5px] flex size-[26px] items-center justify-center rounded-full">
            <span class="menu-fold" aria-hidden="true"><span></span><span></span></span>
        </span>
    </button>

    <div x-cloak x-show="open"
         class="absolute top-0 left-0 z-40 flex w-[235px] flex-col rounded-[15px] border-[1.5px] border-black/15 bg-white pt-[6.5px] pb-[8.5px]">
        <button type="button" @click="open = false" class="group hover-tint absolute top-[14.5px] left-[200.5px] z-10 flex size-[26px] items-center justify-center rounded-full" aria-label="Close account menu">
            <span class="menu-fold" :class="fold ? 'menu-fold-x' : ''" aria-hidden="true"><span></span><span></span></span>
        </button>

        <div class="relative h-[41px] pl-[9.5px]">
            <x-app.avatar :user="$user" class="absolute top-[2px] left-[9.5px]" />
            <p class="absolute top-0 left-[51.5px] text-[16px] font-bold leading-[20px]">{{ $user->username }}</p>
            <p class="absolute top-[20px] left-[51.5px] text-[13px] font-medium leading-[17px] text-subtle">{{ $user->roleShortName() }}</p>
        </div>

        @foreach ($others as $other)
            <form method="POST" action="{{ route('account.switch') }}" class="{{ $loop->first ? 'mt-[16px]' : 'mt-[10px]' }}">
                @csrf
                <input type="hidden" name="username" value="{{ $other->username }}">
                <button type="submit" class="group hover-tint relative block h-[41px] w-full rounded-[10px] text-left" title="Sign in as {{ $other->username }}">
                    <x-app.avatar :user="$other" class="absolute top-[2px] left-[9.5px]" />
                    {{-- A switch ring around accounts you can jump to: still at rest, it spins while hovered. --}}
                    <svg class="switch-ring pointer-events-none absolute top-[-2px] left-[5.5px] size-[44px]" viewBox="0 0 44 44" fill="none" aria-hidden="true">
                        <circle cx="22" cy="22" r="21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="22 5" />
                        <path d="M36.9 1.6v5.6h-5.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span class="absolute top-0 left-[51.5px] text-[16px] font-bold leading-[20px]">{{ $other->username }}</span>
                    <span class="absolute top-[20px] left-[51.5px] text-[13px] font-medium leading-[17px] text-subtle">{{ $other->roleShortName() }}</span>
                </button>
            </form>
        @endforeach

        <div class="divider mt-[7px] ml-[10.5px] w-[210px]"></div>

        <form method="POST" action="{{ route('logout') }}" class="mt-[10.5px] pl-[7.5px]">
            @csrf
            <button type="submit" class="bg-brand-logout gradient-button h-[39px] w-[215px] rounded-[30px] text-[20px] font-bold text-white">Log out</button>
        </form>
    </div>
</div>
