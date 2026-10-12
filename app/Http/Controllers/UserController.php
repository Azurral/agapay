<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->queryText('role');
        $search = $request->queryText('q');

        $users = User::with('role')
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';
                $query->where(fn ($q) => $q->where('username', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->when($role === 'none', fn ($query) => $query->whereNull('role_id'))
            ->when($role !== '' && $role !== 'none', fn ($query) => $query->whereHas('role', fn ($r) => $r->where('slug', $role)))
            ->orderBy('id')
            ->get();

        $roles = Role::with('permissions:id,slug')->orderBy('id')->get();

        return view('users.index', [
            'users' => $users,
            'roles' => $roles,
            'permissions' => Permission::orderBy('id')->get()->groupBy('group'),
            'roleOptions' => ['' => 'All'] + $roles->pluck('name', 'slug')->all() + ['none' => 'No Role'],
            'openAdd' => $request->boolean('add') || session('errors')?->getBag('createUser')->any(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        // The administrator chose this password, so the person picks their own at the first sign-in.
        $user = new User($request->validated());
        $user->must_change_password = true;
        $user->save();

        return redirect()->route('users.index')->with('status', "{$user->username} was added.");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->fill($data);
        // A password reset by the administrator is temporary; changing your own here needs no second step.
        if (isset($data['password']) && ! $user->is($request->user())) {
            $user->must_change_password = true;
        }
        $user->save();

        return redirect()->route('users.index')->with('status', "{$user->username} was updated.");
    }
}
