@props(['user'])
{{-- Initials in the role's colour (no uploaded photos: nothing personal to store). The colour theme can set each role's
     colour (app.css --avatar-<role>); the violet originals are the fallback. --}}
<span {{ $attributes->class('flex size-[36px] items-center justify-center rounded-full text-[14px] font-bold leading-none text-white') }}
      style="background-color: var(--avatar-{{ $user->role?->slug ?? 'none' }}, {{ $user->avatarColor() }})" title="{{ $user->name ?: $user->username }}" aria-hidden="true">{{ $user->initials() }}</span>
