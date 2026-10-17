{{-- Last name, first name, M.I. and suffix, as on the office forms. $values: the four parts; $errorsFor: fn (field) => message|null --}}
@php($box = 'flex h-[44px] items-center rounded-[10px] border bg-white px-[16px] text-[14px] font-bold leading-[20px]')
<x-ui.inline-field label="Last Name" name="last_name" :value="$values['last_name']" placeholder="e.g. Santos" :error="$errorsFor('last_name')" required maxlength="100" autocomplete="family-name" />
<x-ui.inline-field label="First Name" name="first_name" :value="$values['first_name']" placeholder="e.g. Catherine" :error="$errorsFor('first_name')" required maxlength="100" autocomplete="given-name" />
<div class="grid grid-cols-[120px_1fr] gap-[12px]">
    <x-ui.inline-field label="M.I." name="middle_initial" :value="$values['middle_initial']" placeholder="M" :error="$errorsFor('middle_initial')" maxlength="2" />
    <div>
        <label class="{{ $box }} {{ $errorsFor('suffix') ? 'border-bad' : 'border-field' }}">
            <span class="shrink-0">Suffix:</span>
            <select name="suffix" class="ml-[6px] h-full min-w-0 flex-1 cursor-pointer bg-transparent font-bold outline-none">
                <option value="">None</option>
                @foreach (\App\Models\User::SUFFIXES as $suffix)
                    <option value="{{ $suffix }}" @selected($values['suffix'] === $suffix)>{{ $suffix }}</option>
                @endforeach
            </select>
        </label>
        @if ($errorsFor('suffix'))
            <p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $errorsFor('suffix') }}</p>
        @endif
    </div>
</div>
