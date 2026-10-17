{{-- Live checklist under a new password. Needs an Alpine scope with `pw` and `pw2` (x-model on the two boxes). --}}
<ul {{ $attributes->class('flex flex-wrap gap-x-[18px] gap-y-[4px] pl-[4px] text-[12.5px] font-semibold') }} aria-live="polite">
    <li class="pw-rule" :class="pw.length >= 8 && 'is-met'">
        <span class="pw-rule-mark" aria-hidden="true" x-text="pw.length >= 8 ? '✓' : '•'">•</span> At least 8 characters
    </li>
    <li class="pw-rule" :class="pw !== '' && pw === pw2 && 'is-met'">
        <span class="pw-rule-mark" aria-hidden="true" x-text="pw !== '' && pw === pw2 ? '✓' : '•'">•</span> The two passwords match
    </li>
</ul>
