<form method="POST" action="{{ route('account.switch') }}"
      {{ $attributes->class('bg-brand-card relative block h-[96px] w-[211px] rounded-[15px]') }}>
    @csrf
    <p class="absolute top-[12px] left-[14px] text-[15px] font-bold leading-[18px] text-white">RETURN TO LOGIN</p>
    <p class="absolute top-[31px] left-[14px] w-[185px] text-[12px] leading-[16px] text-white">Go back to the login screen</p>
    <button type="submit" class="absolute top-[56px] left-[14px] h-[28px] w-[183px] invert-button rounded-[50px] bg-white text-[15px] font-bold hover:bg-[#4b32c3] hover:text-white">Switch</button>
</form>
