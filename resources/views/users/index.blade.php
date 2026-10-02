@php($editBag = $errors->getBag('editUser'))
<x-layouts.app title="USER MANAGEMENT">
    @if (session('status'))
        <p class="rounded-[10px] border-[1.5px] border-ok bg-white px-[16px] py-[10px] text-[14px] font-bold" role="status">{{ session('status') }}</p>
    @endif

    <x-ui.card title="User Management">
        <form method="GET" class="mt-[17.5px] flex items-center gap-[22px]">
            <x-ui.pill-input name="q" placeholder="Search by name..." :value="request()->queryText('q')" width="272" />
            <x-ui.pill-select name="role" label="Role" :options="$roleOptions" :selected="request()->queryText('role')" width="280" />
            <span class="ml-auto w-[155px] text-center text-[14px] font-medium text-muted">Status</span>
            <button type="submit" class="sr-only">Apply filters</button>
        </form>

        <div class="divider mt-[12px]"></div>

        <ul class="mt-[6.5px] flex flex-col gap-[6px]">
            @forelse ($users as $user)
                <li x-data>
                    <button type="button" @click="$dispatch('open-modal', 'edit-user-{{ $user->id }}')"
                            class="grid h-[39px] w-full grid-cols-[297px_1fr_155px] items-center rounded-[10px] text-left text-[14px] font-bold hover-tint">
                        <span class="pl-[15px]">{{ $user->username }}</span>
                        <span>{{ $user->roleName() }}</span>
                        <x-ui.status-chip :tone="$user->isActive() ? 'ok' : 'bad'">{{ $user->isActive() ? 'Active' : 'Inactive' }}</x-ui.status-chip>
                    </button>
                </li>
            @empty
                <li class="py-[12px] pl-[15px] text-[14px] font-bold">No users match these filters.</li>
            @endforelse
        </ul>
    </x-ui.card>

    <div>
        <button type="button" x-data @click="$dispatch('open-modal', 'configure-roles')"
                class="hover-tint ml-[-3px] flex h-[39px] w-[263px] items-center justify-center gap-[2px] rounded-[50px] border-[1.5px] border-brand-soft bg-white hover:bg-[#efeaff] hover:text-brand text-[20px] font-bold leading-[24px]">
            <img src="{{ asset('images/figma/icons/plus.svg') }}" alt="" class="size-[24px]"> Configure Roles
        </button>
    </div>

    {{-- Add User --}}
    <x-ui.modal name="add-user" title="Add User" :open="$openAdd">
        <form method="POST" action="{{ route('users.store') }}" class="grid grid-cols-[300px_1fr] gap-x-[16px] gap-y-[12px]">
            @csrf
            <x-ui.inline-field label="Full Name" name="name" :value="old('name')" placeholder="e.g. Rosa Mendez" :error="$errors->createUser->first('name')" required />
            <x-ui.inline-field label="Username" name="username" :value="old('username')" placeholder="e.g. Encoder_05" :error="$errors->createUser->first('username')" required />
            <x-ui.inline-field label="Password" name="password" type="password" placeholder="At least 8 characters" :error="$errors->createUser->first('password')" required />
            <x-ui.inline-field label="Confirm" name="password_confirmation" type="password" placeholder="Repeat password" required />
            @include('users.partials.role-status-fields', ['bag' => 'createUser', 'roleId' => old('role_id'), 'status' => old('status', 'active')])
            <x-ui.gradient-button class="col-span-2 mt-[20px]">Save User</x-ui.gradient-button>
        </form>
    </x-ui.modal>

    {{-- Edit User (one per row, re-opened after a failed save) --}}
    @foreach ($users as $user)
        @php($failed = $editBag->any() && (int) old('user_id') === $user->id)
        <x-ui.modal name="edit-user-{{ $user->id }}" title="Edit {{ $user->username }}" :open="$failed">
            <form method="POST" action="{{ route('users.update', $user) }}" class="grid grid-cols-[300px_1fr] gap-x-[16px] gap-y-[12px]">
                @csrf @method('PUT')
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <x-ui.inline-field label="Full Name" name="name" :value="$failed ? old('name') : $user->name" :error="$failed ? $editBag->first('name') : null" required />
                <x-ui.inline-field label="Username" name="username" :value="$failed ? old('username') : $user->username" :error="$failed ? $editBag->first('username') : null" required />
                <x-ui.inline-field label="New Password" name="password" type="password" placeholder="Leave blank to keep" :error="$failed ? $editBag->first('password') : null" />
                <x-ui.inline-field label="Confirm" name="password_confirmation" type="password" placeholder="Repeat new password" />
                @include('users.partials.role-status-fields', [
                    'bag' => 'editUser',
                    'failed' => $failed,
                    'roleId' => $failed ? old('role_id') : $user->role_id,
                    'status' => $failed ? old('status') : $user->status,
                ])
                <x-ui.gradient-button class="col-span-2 mt-[20px]">Save Changes</x-ui.gradient-button>
            </form>
        </x-ui.modal>
    @endforeach

    {{-- Configure Roles: permission matrix --}}
    <x-ui.modal name="configure-roles" title="Configure Roles" width="900">
        <form method="POST" action="{{ route('roles.permissions.update') }}">
            @csrf @method('PUT')
            <div class="max-h-[520px] overflow-y-auto">
                <table class="w-full text-left text-[14px]">
                    <thead class="sticky top-0 bg-white text-muted">
                        <tr class="h-[39px] font-medium">
                            <th class="pl-[4px]">Permission</th>
                            @foreach ($roles as $role)
                                <th class="w-[150px] text-center">{{ $role->short_name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach ($permissions as $group => $groupPermissions)
                        <tbody>
                            <tr><th colspan="{{ 1 + $roles->count() }}" class="divider pt-[10px] pb-[4px] pl-[4px] font-bold">{{ $group }}</th></tr>
                            @foreach ($groupPermissions as $permission)
                                <tr class="h-[36px] font-bold">
                                    <td class="pl-[4px]">{{ $permission->label }}</td>
                                    @foreach ($roles as $role)
                                        @php($locked = in_array($permission->slug, \App\Support\PermissionCatalog::ALWAYS_GRANTED, true) || ($role->slug === \App\Models\Role::ADMIN && in_array($permission->slug, \App\Support\PermissionCatalog::LOCKED_FOR_ADMIN, true)))
                                        <td class="text-center">
                                            <input type="checkbox" name="permissions[{{ $role->id }}][]" value="{{ $permission->slug }}"
                                                   @checked($locked || $role->permissions->contains('slug', $permission->slug))
                                                   @disabled($locked) aria-label="{{ $role->name }}: {{ $permission->label }}"
                                                   class="size-[18px] accent-brand">
                                            @if ($locked)
                                                <input type="hidden" name="permissions[{{ $role->id }}][]" value="{{ $permission->slug }}">
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
            <x-ui.gradient-button class="mt-[20px]">Save Role Permissions</x-ui.gradient-button>
        </form>
    </x-ui.modal>
</x-layouts.app>
