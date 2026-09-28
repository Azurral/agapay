@props(['user'])
@php($others = \App\Support\KnownAccounts::others(request(), $user))
<div x-data="{ open: false }" class="relative h-[56px] w-[235px]" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" @click="open = true" :aria-expanded="open" aria-haspopup="true"
            class="relative block h-[56px] w-[235px] rounded-[15px] border-[1.5px] border-black/15 bg-white text-left">
        <img src="{{ $user->avatarUrl() }}" alt="" class="absolute top-[8.5px] left-[9.5px] size-[36px] rounded-full object-cover">
        <span class="absolute top-[5px] left-[50px] text-[16px] font-bold leading-[20px]">{{ $user->username }}</span>
        <span class="absolute top-[25px] left-[50px] text-[13px] font-medium leading-[17px] text-subtle">{{ $user->roleShortName() }}</span>
        <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="absolute top-[17px] left-[203px] size-[18px]">
    </button>

    <div x-cloak x-show="open" x-transition.opacity
         class="absolute top-0 left-0 z-40 flex w-[235px] flex-col rounded-[15px] border-[1.5px] border-black/15 bg-white pt-[6.5px] pb-[8.5px]">
        <button type="button" @click="open = false" class="absolute top-[18.5px] left-[204.5px] size-[18px]" aria-label="Close account menu">
            <img src="{{ asset('images/figma/icons/arrow-up.svg') }}" alt="">
        </button>

        <div class="relative h-[41px] pl-[9.5px]">
            <img src="{{ $user->avatarUrl() }}" alt="" class="absolute top-[2px] left-[9.5px] size-[36px] rounded-full object-cover">
            <p class="absolute top-0 left-[51.5px] text-[16px] font-bold leading-[20px]">{{ $user->username }}</p>
            <p class="absolute top-[20px] left-[51.5px] text-[13px] font-medium leading-[17px] text-subtle">{{ $user->roleShortName() }}</p>
        </div>

        @foreach ($others as $other)
            <form method="POST" action="{{ route('account.switch') }}" class="mt-[10px]">
                @csrf
                <input type="hidden" name="username" value="{{ $other->username }}">
                <button type="submit" class="relative block h-[41px] w-full text-left" title="Sign in as {{ $other->username }}">
                    <img src="{{ $other->avatarUrl() }}" alt="" class="absolute top-[2px] left-[9.5px] size-[36px] rounded-full object-cover">
                    <img src="{{ asset('images/figma/icons/ring.svg') }}" alt="" class="absolute top-[2.5px] left-[10.5px]">
                    <span class="absolute top-0 left-[51.5px] text-[16px] font-bold leading-[20px]">{{ $other->username }}</span>
                    <span class="absolute top-[20px] left-[51.5px] text-[13px] font-medium leading-[17px] text-subtle">{{ $other->roleShortName() }}</span>
                </button>
            </form>
        @endforeach

        <div class="divider mt-[7px] ml-[10.5px] w-[210px]"></div>

        <form method="POST" action="{{ route('logout') }}" class="mt-[12px] pl-[7.5px]">
            @csrf
            <button type="submit" class="bg-brand-logout h-[39px] w-[215px] rounded-[30px] text-[20px] font-bold text-white">Log out</button>
        </form>
    </div>
</div>
