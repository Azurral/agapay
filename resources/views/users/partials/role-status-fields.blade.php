@php($roles = \App\Models\Role::orderBy('id')->get())
@php($error = ($failed ?? true) ? $errors->getBag($bag)->first('role_id') : null)
<div>
    <label class="flex h-[44px] items-center rounded-[10px] border bg-white px-[16px] text-[14px] font-bold {{ $error ? 'border-bad' : 'border-field' }}">
        <span class="shrink-0">Role:</span>
        <select name="role_id" class="ml-[6px] h-full flex-1 cursor-pointer bg-transparent font-bold outline-none">
            <option value="">No Role</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) $roleId === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
    </label>
    @if ($error)
        <p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $error }}</p>
    @endif
</div>
<label class="flex h-[44px] items-center rounded-[10px] border border-field bg-white px-[16px] text-[14px] font-bold">
    <span class="shrink-0">Status:</span>
    <select name="status" class="ml-[6px] h-full flex-1 cursor-pointer bg-transparent font-bold outline-none">
        <option value="active" @selected($status === 'active')>Active</option>
        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
    </select>
</label>
