@props(['source', 'active'])
{{-- Figma 344:230: Beneficiaries | Archived/Restore. --}}
<nav class="flex h-[39px] w-[327px] items-center rounded-[50px] bg-[linear-gradient(90deg,#7e80ff_0%,#5a5de3_55%,#4b4fc4_100%)] px-[8px]" aria-label="Records or archive">
    @php($recordsLink = $source === 'lgu' ? ['Records', route('interventions.lgu', ['tab' => 'records'])] : ['Beneficiaries', route("interventions.{$source}")])
    @foreach (['records' => $recordsLink, 'archived' => ['Archived/Restore', Route::has('interventions.archived') ? route('interventions.archived', $source) : '#']] as $key => [$label, $url])
        <a href="{{ $url }}" @if ($active === $key) aria-current="page" @endif
           @class([
               'flex h-[25px] flex-1 items-center justify-center rounded-[50px] text-[16px] font-bold leading-[20px] transition-colors',
               'bg-white text-ink' => $active === $key,
               'text-white hover:bg-white/20' => $active !== $key,
           ])>{{ $label }}</a>
    @endforeach
</nav>
