<form method="POST" action="{{ route('account.switch') }}"
      {{ $attributes->class('bg-brand-card relative block h-[129px] w-[203px] rounded-[15px]') }}>
    @csrf
    <p class="absolute top-[14px] left-[14px] text-[16px] font-bold leading-[15px] text-white">RETURN TO LOGIN</p>
    <p class="absolute top-[34px] left-[14px] w-[152px] text-[13px] leading-[17px] text-white">Return and go back to the login screen</p>
    <button type="submit" class="absolute top-[85px] left-[14px] h-[31px] w-[175px] rounded-[50px] bg-white text-[16px] font-bold">Switch</button>
</form>
