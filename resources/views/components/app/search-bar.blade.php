<form method="GET" role="search" action="{{ Route::has('search') ? route('search') : url()->current() }}"
      {{ $attributes->class('bg-brand-bar flex h-[59px] items-center rounded-[30px] pr-[11px] pl-[28px]') }}>
    <input type="search" name="q" value="{{ request()->queryText('q') }}" aria-label="Search beneficiaries"
           placeholder="Search beneficiary by name, RSBSA No., or barangay..."
           class="h-full min-w-0 flex-1 bg-transparent text-[20px] font-bold text-white outline-none placeholder:text-white">
    <button type="button" class="h-[39px] w-[176px] shrink-0 rounded-[50px] bg-white text-[20px] font-bold">Filter</button>
</form>
