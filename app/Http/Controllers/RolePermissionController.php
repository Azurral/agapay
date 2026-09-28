<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $submitted = (array) $request->input('permissions', []);
        $permissionIds = Permission::pluck('id', 'slug');

        DB::transaction(function () use ($submitted, $permissionIds) {
            foreach (Role::with('permissions:id,slug')->get() as $role) {
                $slugs = collect((array) ($submitted[$role->id] ?? []))
                    ->filter(fn ($slug) => is_string($slug) && $permissionIds->has($slug));

                if ($role->slug === Role::ADMIN) {
                    $slugs = $slugs->merge(PermissionCatalog::LOCKED_FOR_ADMIN);
                }

                $new = $slugs->unique()->sort()->values()->all();
                $old = $role->permissions->pluck('slug')->sort()->values()->all();

                if ($new === $old) {
                    continue;
                }

                $role->permissions()->sync($permissionIds->only($new)->values());
                AuditLogger::record('Configured Roles', $role, $role->name, ['permissions' => $old], ['permissions' => $new]);
            }
        });

        return redirect()->route('users.index')->with('status', 'Role permissions saved.');
    }
}
