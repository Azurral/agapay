<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Every permission slug is a gate ability: can:users.manage, @can('audit.view'), ...
        Gate::before(fn (User $user, string $ability) => $user->hasPermission($ability) ? true : null);
    }
}
