<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
        // Query-string text filter: arrays (?q[]=x) and non-strings become '' instead of a 500.
        Request::macro('queryText', function (string $key): string {
            $value = $this->query($key);

            return is_string($value) ? trim($value) : '';
        });

        // Every permission slug is a gate ability: can:users.manage, @can('audit.view'), ...
        Gate::before(fn (User $user, string $ability) => $user->hasPermission($ability) ? true : null);

        Event::listen(Login::class, fn (Login $event) => AuditLogger::record('Logged In', $event->user, $event->user->username, actor: $event->user));
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                AuditLogger::record('Logged Out', $event->user, $event->user->username, actor: $event->user);
            }
        });
    }
}
