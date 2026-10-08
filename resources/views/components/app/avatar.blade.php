@props(['user'])
{{-- Initials in the role's colour (no uploaded photos: nothing personal to store). --}}
<span {{ $attributes->class('flex size-[36px] items-center justify-center rounded-full text-[14px] font-bold leading-none text-white') }}
      style="background-color: {{ $user->avatarColor() }}" title="{{ $user->name ?: $user->username }}" aria-hidden="true">{{ $user->initials() }}</span>
