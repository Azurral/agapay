<button {{ $attributes->merge(['type' => 'submit'])->class('bg-brand-button gradient-button h-[48px] w-full rounded-[50px] text-[20px] font-bold leading-[24px] text-white') }}>
    {{ $slot }}
</button>
