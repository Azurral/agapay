@props(['label' => 'Agapay logo'])
@php($gradient = 'agapay-plot-'.\Illuminate\Support\Str::random(6))
{{-- The logo (public/images/logo.svg) drawn inline so its bars can grow one after another on load and bounce on hover. --}}
<svg {{ $attributes->class('agapay-logo') }} viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg"
     @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif>
    <defs>
        <linearGradient id="{{ $gradient }}" x1="8" y1="92" x2="92" y2="20" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#8037FF"/>
            <stop offset="0.55" stop-color="#4671FF"/>
            <stop offset="1" stop-color="#0BCAFF"/>
        </linearGradient>
    </defs>
    <rect class="logo-bar" style="--i: 0" x="8" y="62" width="24" height="30" rx="6.24" fill="url(#{{ $gradient }})"/>
    <rect class="logo-bar" style="--i: 1" x="38" y="42" width="24" height="50" rx="6.24" fill="url(#{{ $gradient }})"/>
    <rect class="logo-bar" style="--i: 2" x="68" y="30" width="24" height="62" rx="6.24" fill="url(#{{ $gradient }})"/>
    {{-- The leaf rides on the tallest bar's top (same timing), so the bar never grows into it. --}}
    <g class="logo-ride">
        <path class="logo-leaf" d="M69.26 27C69.26 13.11 78.11 4.26 92 4.26C92 18.16 83.16 27 69.26 27Z" fill="#22D3FF"/>
    </g>
</svg>
