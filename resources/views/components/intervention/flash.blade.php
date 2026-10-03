{{-- Status and rule messages from the intervention actions. --}}
@if (session('status'))
    <p class="rounded-[10px] border-[1.5px] border-ok bg-white px-[16px] py-[10px] text-[14px] font-bold" role="status">{{ session('status') }}</p>
@endif
@if ($errors->intervention->any())
    <p class="rounded-[10px] border-[1.5px] border-bad bg-white px-[16px] py-[10px] text-[14px] font-bold text-danger" role="alert">{{ $errors->intervention->first() }}</p>
@endif
