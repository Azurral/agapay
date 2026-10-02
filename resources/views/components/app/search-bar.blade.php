@php
    $barangays = \App\Models\Barangay::orderBy('name')->get(['id', 'name']);
    $statuses = collect(\App\Http\Controllers\SearchController::STATUSES)
        ->mapWithKeys(fn ($s) => [$s => \App\Models\Beneficiary::rsbsaStatusLabel($s)]);
    $filtered = request()->queryText('barangay') !== '' || request()->queryText('rsbsa_status') !== '';
@endphp
<form method="GET" role="search" action="{{ route('search') }}" x-data="{ filters: false }" @keydown.escape="filters = false"
      {{ $attributes->class('bg-brand-bar relative flex h-[59px] items-center rounded-[30px] pr-[11px] pl-[28px]') }}>
    <input type="search" name="q" value="{{ request()->queryText('q') }}" aria-label="Search beneficiaries" maxlength="100"
           placeholder="Search beneficiary by name, RSBSA No., or barangay..."
           class="h-full min-w-0 flex-1 bg-transparent text-[20px] font-bold text-white outline-none placeholder:text-white">
    <button type="button" @click="filters = ! filters" :aria-expanded="filters" aria-controls="search-filters"
            class="h-[39px] w-[176px] shrink-0 hover-tint rounded-[50px] bg-white text-[20px] font-bold hover:bg-[#efeaff] hover:text-brand">Filter{{ $filtered ? ' •' : '' }}</button>

    <div id="search-filters" x-cloak x-show="filters" @click.outside="filters = false"
         class="absolute top-[66px] right-0 z-40 flex w-[360px] flex-col gap-[14px] rounded-[15px] border-[1.5px] border-black/10 bg-white p-[20px] shadow-[0_20px_50px_rgba(90,93,227,0.25),0_4px_12px_rgba(0,0,0,0.08)]">
        @foreach ([['barangay', 'Barangay', $barangays->pluck('name', 'id')], ['rsbsa_status', 'RSBSA Status', $statuses]] as [$field, $label, $options])
            <label class="flex flex-col gap-[7px] text-[16px] font-bold leading-[20px]">
                {{ $label }}
                <span class="relative">
                    <select name="{{ $field }}" class="h-[39px] w-full cursor-pointer appearance-none rounded-[15px] bg-brand-soft/10 px-[16px] text-[14px] font-medium text-ink outline-none">
                        <option value="">All</option>
                        @foreach ($options as $value => $text)
                            <option value="{{ $value }}" @selected(request()->queryText($field) === (string) $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                    <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="pointer-events-none absolute top-[10.5px] right-[14px] size-[18px]">
                </span>
            </label>
        @endforeach
        <button type="submit" class="bg-brand-bar gradient-button h-[44px] rounded-[50px] text-[16px] font-bold text-white">Apply</button>
    </div>
</form>
