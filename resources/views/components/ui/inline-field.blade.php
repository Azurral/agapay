@props(['label', 'name', 'type' => 'text', 'value' => null, 'placeholder' => '', 'error' => null])
<div>
    <label class="flex h-[44px] items-center rounded-[10px] border bg-white px-[16px] text-[14px] font-bold leading-[20px] {{ $error ? 'border-bad' : 'border-field' }}">
        <span class="shrink-0">{{ $label }}:</span>
        <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}"
               {{ $attributes->class('ml-[6px] h-full min-w-0 flex-1 bg-transparent font-bold outline-none placeholder:text-muted') }}>
    </label>
    @if ($error)
        <p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $error }}</p>
    @endif
</div>
