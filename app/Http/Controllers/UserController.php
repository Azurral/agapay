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
        $role = (string) $request->query('role', '');

        $users = User::with('role')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->query('q').'%';
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
        $user = User::create($request->validated());

        return redirect()->route('users.index')->with('status', "{$user->username} was added.");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('status', "{$user->username} was updated.");
    }
}
